<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the Facturis Online API
 * (facturis-online.ro/api). Mirrors Facturis' method names (saveInvoice,
 * listInvoices, saveClient, saveProduct) and snake_case payload
 * (client_name, client_cui, products[] with denumire/pret/cota_tva) and maps
 * them to Brivio's native `/v1` API (buyer upserted via `/contacts` +
 * `external_id`, then `POST /invoices` with `contact_external_id` — see
 * NativeInvoiceBody). `client_platitor_tva` decides whether the CUI is sent
 * as `RO<cif>` or bare digits. `trimite_efactura` is not honoured here: the
 * invoice is created as a DRAFT; issue it and submit it with the native
 * client() (`sendInvoice`, then `submitInvoiceToANAF`).
 *
 * Facturis original (REST, POST to save_invoice.php):
 *   $data = [
 *     'client_name' => 'ACME SRL', 'client_cui' => 'RO123',
 *     'serie' => 'FAC',
 *     'products' => [['denumire' => 'X', 'cantitate' => 1, 'pret' => 100, 'cota_tva' => 21]],
 *   ];
 *
 * Brivio drop-in:
 *   $api = new \Brivio\Legacy\Facturis('brivio_sk_live_...');
 *   $api->saveInvoice($data);   // same shape
 *
 * @phpstan-type FacturisProduct array{denumire?:string, nume?:string, cantitate?:float, pret?:float, cota_tva?:float, um?:string, tip?:string, tva_inclus?:bool}
 */
final class Facturis
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

    /** Facturis: setarea CUI-ului firmei emitente. */
    public function setCui(string $cui): void
    {
        $this->cif = $cui;
    }

    /** The CUI set with setCui() (informational; the API issues as the org's company). */
    public function getCui(): ?string
    {
        return $this->cif;
    }

    /**
     * Facturis: save_invoice / saveInvoice.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveInvoice(array $data): array
    {
        return $this->createNativeInvoice($this->mapClient($data), $this->mapDocument($data, 'INVOICE'));
    }

    /**
     * Facturis: save_proforma.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveProforma(array $data): array
    {
        return $this->createNativeInvoice($this->mapClient($data), $this->mapDocument($data, 'PROFORMA'));
    }

    /**
     * Facturis: list_invoices / listInvoices.
     *
     * @return array{data: list<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listInvoices(): array
    {
        return $this->brivio->listInvoices();
    }

    /**
     * Facturis: save_client / saveClient. Idempotent: an existing contact with
     * the same derived `external_id` is returned as is.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveClient(array $data): array
    {
        return $this->upsertNativeContact($this->nativeContactBody($this->mapClient($data)));
    }

    /**
     * Facturis: save_product / saveProduct.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveProduct(array $data): array
    {
        return $this->brivio->createArticle([
            'name' => $data['denumire'] ?? $data['nume'] ?? '',
            'unit_price' => (float) ($data['pret'] ?? 0),
            'vat_rate' => (float) ($data['cota_tva'] ?? 0),
            'unit' => $data['um'] ?? 'buc',
            'is_service' => ($data['tip'] ?? null) === 'serviciu',
        ]);
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapClient(array $p): array
    {
        return [
            'name' => (string) ($p['client_name'] ?? $p['client_nume'] ?? $p['nume'] ?? ''),
            'vat_code' => $p['client_cui'] ?? $p['cui'] ?? null,
            'vat_payer' => self::flag($p['client_platitor_tva'] ?? $p['platitor_tva'] ?? null),
            'registration_number' => $p['client_rc'] ?? $p['nr_reg_com'] ?? null,
            'email' => $p['client_email'] ?? $p['email'] ?? null,
            'phone' => $p['client_telefon'] ?? $p['telefon'] ?? null,
            'street' => $p['client_adresa'] ?? $p['adresa'] ?? null,
            'city' => $p['client_oras'] ?? $p['oras'] ?? null,
            'county' => $p['client_judet'] ?? $p['judet'] ?? null,
            'country' => $p['client_tara'] ?? $p['tara'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapDocument(array $p, string $type): array
    {
        /** @var list<FacturisProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'description' => (string) ($row['denumire'] ?? $row['nume'] ?? ''),
                'quantity' => $row['cantitate'] ?? 1,
                'unit_price' => $row['pret'] ?? 0,
                'vat_rate' => $row['cota_tva'] ?? null,
                'vat_included' => self::flag($row['tva_inclus'] ?? null) ?? false,
                'unit' => $row['um'] ?? null,
            ];
        }, $products);

        return [
            'type' => $type,
            'series' => $p['serie'] ?? null,
            'issue_date' => $p['data_factura'] ?? $p['date_invoice'] ?? null,
            'due_date' => $p['data_scadenta'] ?? null,
            'currency' => $p['moneda'] ?? null,
            'exchange_rate' => $p['curs_valutar'] ?? null,
            'notes' => $p['observatii'] ?? $p['mentiuni'] ?? null,
            'lines' => $lines,
        ];
    }
}
