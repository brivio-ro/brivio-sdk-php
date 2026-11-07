<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Shared translation from the legacy-provider shims to Brivio's NATIVE
 * `POST /invoices` body (`InvoiceCreate` in docs/brivio-openapi.json).
 *
 * `POST /invoices` takes no inline buyer: it needs `contact_id` or
 * `contact_external_id`, and an unknown external id is a 404. So every shim
 * invoice is two steps:
 *
 *   1. upsert the buyer: `GET /contacts?external_id=…`, and `POST /contacts`
 *      with that `external_id` only when nothing matches;
 *   2. `POST /invoices` with `contact_external_id`.
 *
 * The external id is deterministic and vendor-agnostic (`legacy:cui:<digits>`,
 * `legacy:vat:<code>` or `legacy:name:<hash>`), so the same buyer sent through
 * two shims, or twice, resolves to one contact.
 *
 * RO VAT: a Romanian CUI travels as `RO<digits>` only when the vendor payload
 * marks the buyer as a VAT payer (SmartBill `isTaxPayer`, Oblio `vatPayer`,
 * FGO `PlatitorTVA`, Facturis `client_platitor_tva`); explicitly not a payer →
 * bare digits. When the vendor payload carries no flag at all, the prefix the
 * caller typed is kept, because that prefix is the only mark the vendor had.
 *
 * Classes using this trait must hold `private BrivioClient $brivio`.
 *
 * Intermediate shapes the shims hand to this trait (all keys optional):
 *   contact:  name, vat_code, vat_payer (bool|null), person_type, registration_number,
 *             email, phone, street, city, county, country
 *   document: type, series, issue_date, due_date, currency, exchange_rate, notes, lines[]
 *   line:     description, quantity, unit_price, vat_rate, vat_included (bool), unit
 */
trait NativeInvoiceBody
{
    private ?string $companyId = null;

    /**
     * Issuing legal entity (`company_id`). Needed only when the organisation
     * has more than one active company; the vendor's own seller CIF field
     * cannot be used for this because the API identifies companies by id.
     */
    public function useCompany(string $companyId): void
    {
        $this->companyId = $companyId;
    }

    /**
     * Upsert the buyer, then create the invoice through `POST /invoices`.
     *
    * @param array<string,mixed> $contact
    * @param array<string,mixed> $document
     * @return array<string,mixed>
     */
    private function createNativeInvoice(array $contact, array $document): array
    {
        $contactBody = $this->nativeContactBody($contact);
        $this->upsertNativeContact($contactBody);
        return $this->brivio->createInvoice($this->nativeInvoiceBody($document, (string) $contactBody['external_id']));
    }

    /**
     * Returns the organisation's contact carrying the body's `external_id`,
     * creating it when none does yet.
     *
     * @param array<string,mixed> $contactBody body built by nativeContactBody()
     * @return array<string,mixed>
     */
    private function upsertNativeContact(array $contactBody): array
    {
        $existing = $this->brivio->listContacts(['external_id' => (string) $contactBody['external_id'], 'perPage' => 1]);
        return $existing['data'][0] ?? $this->brivio->createContact($contactBody);
    }

    /**
     * `POST /contacts` body (gateway createContactSchema). Null fields are
     * omitted: the gateway's optional fields reject an explicit null.
     *
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private function nativeContactBody(array $c): array
    {
        $payer = $c['vat_payer'] ?? null;
        $vat = self::roVatNumber($c['vat_code'] ?? null, is_bool($payer) ? $payer : null);
        $personType = strtoupper((string) ($c['person_type'] ?? ''));
        if ($personType !== 'INDIVIDUAL' && $personType !== 'COMPANY') {
            $personType = ($vat !== null && preg_match('/^\d{13}$/', $vat) === 1) ? 'INDIVIDUAL' : 'COMPANY';
        }
        $name = self::text($c['name'] ?? null) ?? '';
        if ($name === '') {
            throw new \InvalidArgumentException('Brivio legacy shim: the buyer (client) needs a name');
        }

        $address = self::withoutNulls([
            'street' => self::text($c['street'] ?? null),
            'city' => self::text($c['city'] ?? null),
            'county' => self::text($c['county'] ?? null),
        ]);
        if ($address !== []) {
            $address['country'] = strtoupper(self::text($c['country'] ?? null) ?? 'RO');
        }

        return self::withoutNulls([
            'type' => 'CLIENT',
            'person_type' => $personType,
            'name' => $name,
            'email' => self::text($c['email'] ?? null),
            'phone' => self::text($c['phone'] ?? null),
            'vat_number' => $vat,
            'registration_number' => self::text($c['registration_number'] ?? null),
            'address' => $address === [] ? null : $address,
            'external_id' => self::contactExternalId($name, $vat),
        ]);
    }

    /**
     * `POST /invoices` body (`InvoiceCreate`).
     *
    * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function nativeInvoiceBody(array $d, string $contactExternalId): array
    {
        $issue = self::isoDate($d['issue_date'] ?? null)
            ?? (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
        $due = self::isoDate($d['due_date'] ?? null) ?? $issue;
        $currency = strtoupper(self::text($d['currency'] ?? null) ?? 'RON');
        $rate = isset($d['exchange_rate']) && is_numeric($d['exchange_rate']) ? (float) $d['exchange_rate'] : null;

        $items = [];
        $lines = is_array($d['lines'] ?? null) ? $d['lines'] : [];
        foreach (array_values($lines) as $i => $line) {
            $items[] = self::nativeItem(is_array($line) ? $line : [], $i);
        }
        if ($items === []) {
            throw new \InvalidArgumentException('Brivio legacy shim: an invoice needs at least one product line');
        }

        return self::withoutNulls([
            'type' => $d['type'] ?? 'INVOICE',
            'direction' => 'ISSUED',
            'series' => self::text($d['series'] ?? null),
            'contact_external_id' => $contactExternalId,
            'company_id' => $this->companyId,
            'issue_date' => $issue,
            'due_date' => $due,
            'currency' => $currency,
            // Never default a rate: omitted → the server fetches BNR T-1 (art. 290).
            'exchange_rate' => ($currency !== 'RON' && $rate !== null && $rate > 0) ? $rate : null,
            'notes' => self::text($d['notes'] ?? null),
            'items' => $items,
        ]);
    }

    /**
     * @param array<mixed> $line
     * @return array<string,mixed>
     */
    private static function nativeItem(array $line, int $index): array
    {
        $description = self::text($line['description'] ?? null) ?? '';
        if ($description === '') {
            throw new \InvalidArgumentException("Brivio legacy shim: product line {$index} has no name");
        }
        $quantity = (float) ($line['quantity'] ?? 1);
        $price = (float) ($line['unit_price'] ?? 0);
        $vatRate = isset($line['vat_rate']) && is_numeric($line['vat_rate']) ? (float) $line['vat_rate'] : 21.0;
        if ($quantity < 0 || $price < 0) {
            throw new \InvalidArgumentException(
                "Brivio legacy shim: product line {$index} has a negative quantity or price; "
                . 'discount lines are not supported, put the discounted price on the line instead',
            );
        }
        // The API takes NET unit prices; a VAT-inclusive vendor price is converted
        // the same way the server-side compat translator does (rounded downstream).
        if (($line['vat_included'] ?? false) && $vatRate > 0) {
            $price = $price / (1 + $vatRate / 100);
        }

        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit' => self::text($line['unit'] ?? null) ?? 'buc',
            'unit_price' => $price,
            'vat_rate' => $vatRate,
        ];
    }

    /**
     * RO CUI with the `RO` prefix only for a VAT payer; non-RO codes and CNPs
     * pass through normalised. `$vatPayer === null` = the vendor sent no flag.
     */
    private static function roVatNumber(mixed $raw, ?bool $vatPayer): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s.\-]/', '', (string) ($raw ?? '')));
        if ($code === '') {
            return null;
        }
        if (preg_match('/^(RO)?(\d{2,10})$/', $code, $m) !== 1) {
            return $code; // CNP (13 digits) or a foreign VAT code: no RO rule applies
        }
        $digits = $m[2];
        $payer = $vatPayer ?? ($m[1] === 'RO');
        return $payer ? 'RO' . $digits : $digits;
    }

    private static function contactExternalId(string $name, ?string $vat): string
    {
        if ($vat !== null && preg_match('/^(?:RO)?(\d{2,10})$/', $vat, $m) === 1) {
            return 'legacy:cui:' . $m[1];
        }
        if ($vat !== null) {
            return 'legacy:vat:' . substr($vat, 0, 200);
        }
        $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        return 'legacy:name:' . substr(hash('sha256', (string) preg_replace('/\s+/', ' ', $key)), 0, 32);
    }

    /** Vendor booleans arrive as bool, 0/1, "1"/"0", "true"/"false", "da"/"nu". */
    private static function flag(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['1', 'true', 'da', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'nu', 'no', 'n'], true)) {
            return false;
        }
        return null;
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    /** Accepts YYYY-MM-DD (optionally with a time part) and DD.MM.YYYY. */
    private static function isoDate(mixed $value): ?string
    {
        $s = self::text($value);
        if ($s === null) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m) === 1) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{2})[.\/](\d{2})[.\/](\d{4})$/', $s, $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        throw new \InvalidArgumentException("Brivio legacy shim: unrecognised date '{$s}' (use YYYY-MM-DD)");
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function withoutNulls(array $values): array
    {
        return array_filter($values, static fn ($v) => $v !== null);
    }
}
