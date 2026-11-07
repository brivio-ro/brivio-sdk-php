<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the FGO API
 * (testapp.fgo.ro/publicws). Mirrors FGO's RO field names (Client, Continut,
 * Serie, TipFactura) and maps them to Brivio's native `/v1` API (buyer
 * upserted via `/contacts` + `external_id`, then `POST /invoices` with
 * `contact_external_id` — see NativeInvoiceBody). `Client.PlatitorTVA`
 * decides whether the CUI is sent as `RO<cif>` or bare digits.
 *
 * FGO original (via teamfurther/fgo-php-sdk style):
 *   $fgo->invoice()->create([... 'Client' => [...], 'Continut' => [...] ]);
 *
 * Brivio drop-in:
 *   $fgo = new \Brivio\Legacy\Fgo('brivio_sk_live_...');
 *   $fgo->createInvoice([... 'Client' => [...], 'Continut' => [...] ]);
 *
 * @phpstan-type FgoLine array{Denumire:string, NrProduse?:float, PretUnitar?:float, CotaTVA?:float, UM?:string}
 */
final class Fgo
{
    use NativeInvoiceBody;

    private BrivioClient $brivio;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.brivio.ro/v1', ?callable $transport = null)
    {
        $this->brivio = new BrivioClient($apiKey, $baseUrl, $transport);
    }

    public function client(): BrivioClient
    {
        return $this->brivio;
    }

    /**
     * FGO: invoice create. Accepts the FGO RO-named payload.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createInvoice(array $payload): array
    {
        $client = is_array($payload['Client'] ?? null) ? $payload['Client'] : [];
        $type = strtolower((string) ($payload['TipFactura'] ?? '')) === 'proforma' ? 'PROFORMA' : 'INVOICE';
        return $this->createNativeInvoice($this->mapClient($client), $this->mapDocument($payload, $type));
    }

    /**
     * FGO clients are created implicitly via the invoice `Client` block; expose
        * a direct mapping too for code that pre-creates contacts. Idempotent: an
        * existing contact with the same derived `external_id` is returned as is.
     *
     * @param array<string,mixed> $fgoClient
     * @return array<string,mixed>
     */
    public function createClient(array $fgoClient): array
    {
        return $this->upsertNativeContact($this->nativeContactBody($this->mapClient($fgoClient)));
    }

    /**
     * @param array<mixed> $client
     * @return array<string,mixed>
     */
    private function mapClient(array $client): array
    {
        $tip = strtoupper((string) ($client['Tip'] ?? 'PJ'));
        return [
            'name' => (string) ($client['Denumire'] ?? ''),
            'person_type' => $tip === 'PF' ? 'INDIVIDUAL' : 'COMPANY',
            'vat_code' => $client['CodUnic'] ?? null,
            'vat_payer' => self::flag($client['PlatitorTVA'] ?? null),
            'registration_number' => $client['NrRegCom'] ?? null,
            'email' => $client['Email'] ?? null,
            'phone' => $client['Telefon'] ?? null,
            'street' => $client['Adresa'] ?? null,
            'city' => $client['Localitate'] ?? null,
            'county' => $client['Judet'] ?? null,
            'country' => $client['Tara'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapDocument(array $p, string $type): array
    {
        /** @var list<FgoLine> $continut */
        $continut = is_array($p['Continut'] ?? null) ? $p['Continut'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'description' => (string) ($row['Denumire'] ?? ''),
                'quantity' => $row['NrProduse'] ?? 1,
                'unit_price' => $row['PretUnitar'] ?? 0,
                'vat_rate' => $row['CotaTVA'] ?? null,
                'unit' => $row['UM'] ?? null,
            ];
        }, $continut);

        return [
            'type' => $type,
            'series' => $p['Serie'] ?? null,
            'issue_date' => $p['DataEmitere'] ?? null,
            'due_date' => $p['DataScadenta'] ?? null,
            'currency' => $p['Valuta'] ?? null,
            'exchange_rate' => $p['CursValutar'] ?? null,
            'notes' => $p['Text'] ?? null,
            'lines' => $lines,
        ];
    }
}
