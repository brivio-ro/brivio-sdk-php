<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the Oblio API
 * (oblio.eu/api). Mirrors Oblio's method names (createInvoice, nomenclature)
 * and payload (cif, client{}, products[]) and maps them to Brivio's native
 * `/v1` API (buyer upserted via `/contacts` + `external_id`, then
 * `POST /invoices` with `contact_external_id` — see NativeInvoiceBody).
 *
 * `cif` / setCif() is kept for source compatibility only: the API identifies
 * the issuing company by id — call useCompany($companyId) when the
 * organisation has more than one. `spvExtern` is not honoured here: the
 * invoice is created as a DRAFT; issue it (`sendInvoice`) and then
 * `submitInvoiceToANAF` on the native client().
 *
 * Oblio original (obliosoftware/oblio-api):
 *   $api = new \OblioSoftware\Api($email, $secret);
 *   $api->setCif($cif);
 *   $api->createInvoice($data);   // data: client{}, products[]
 *
 * Brivio drop-in:
 *   $api = new \Brivio\Legacy\Oblio('brivio_sk_live_...');
 *   $api->setCif($cif);
 *   $api->createInvoice($data);   // same shape
 *
 * @phpstan-type OblioProduct array{name:string, price?:float, measuringUnit?:string, vatPercentage?:float, vatIncluded?:bool, quantity?:float, productType?:string}
 */
final class Oblio
{
    use NativeInvoiceBody;

    private BrivioClient $brivio;
    private ?string $cif = null;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        $this->brivio = new BrivioClient($apiKey, $baseUrl, $transport);
    }

    public function client(): BrivioClient
    {
        return $this->brivio;
    }

    public function setCif(string $cif): void
    {
        $this->cif = $cif;
    }

    /** The CIF set with setCif() (informational; the API issues as the org's company). */
    public function getCif(): ?string
    {
        return $this->cif;
    }

    /**
     * Oblio: createInvoice($data).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createInvoice(array $data): array
    {
        return $this->createNativeInvoice($this->mapClient($data), $this->mapDocument($data, 'INVOICE'));
    }

    /**
     * Oblio: createProforma($data).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createProforma(array $data): array
    {
        return $this->createNativeInvoice($this->mapClient($data), $this->mapDocument($data, 'PROFORMA'));
    }

    /**
     * Oblio: nomenclature('clients'|'products'|...). Maps the common cases to
     * Brivio list endpoints; unsupported types throw.
     *
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function nomenclature(string $type): array
    {
        return match ($type) {
            'clients' => $this->brivio->listContacts(),
            'products' => $this->brivio->listArticles(),
            default => throw new \InvalidArgumentException("Unsupported nomenclature type: {$type}"),
        };
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapClient(array $p): array
    {
        /** @var array<string,mixed> $client */
        $client = is_array($p['client'] ?? null) ? $p['client'] : [];
        return [
            'name' => (string) ($client['name'] ?? ''),
            'vat_code' => $client['cif'] ?? null,
            'vat_payer' => self::flag($client['vatPayer'] ?? null),
            'registration_number' => $client['rc'] ?? null,
            'email' => $client['email'] ?? null,
            'phone' => $client['phone'] ?? null,
            'street' => $client['address'] ?? null,
            'city' => $client['city'] ?? null,
            'county' => $client['state'] ?? null,
            'country' => $client['country'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapDocument(array $p, string $type): array
    {
        /** @var list<OblioProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'description' => (string) ($row['name'] ?? ''),
                'quantity' => $row['quantity'] ?? 1,
                'unit_price' => $row['price'] ?? 0,
                'vat_rate' => $row['vatPercentage'] ?? null,
                'vat_included' => self::flag($row['vatIncluded'] ?? null) ?? false,
                'unit' => $row['measuringUnit'] ?? null,
            ];
        }, $products);

        return [
            'type' => $type,
            'series' => $p['seriesName'] ?? null,
            'issue_date' => $p['issueDate'] ?? null,
            'due_date' => $p['dueDate'] ?? null,
            'currency' => $p['currency'] ?? null,
            'exchange_rate' => $p['exchangeRate'] ?? null,
            'notes' => $p['mentions'] ?? null,
            'lines' => $lines,
        ];
    }
}
