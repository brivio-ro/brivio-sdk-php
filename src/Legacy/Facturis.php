<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the Facturis Online API
 * (facturis-online.ro/api). Mirrors Facturis' method names (saveInvoice,
 * listInvoices, saveClient, saveProduct) and snake_case payload
 * (client_name, client_cui, products[] with denumire/pret/cota_tva) and maps
 * them to Brivio's canonical `/v1` API.
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

    /**
     * Facturis: save_invoice / saveInvoice.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveInvoice(array $data): array
    {
        return $this->brivio->createInvoice($this->mapInvoice($data));
    }

    /** Facturis: save_proforma. */
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveProforma(array $data): array
    {
        $invoice = $this->mapInvoice($data);
        $invoice['kind'] = 'proforma';
        return $this->brivio->createInvoice($invoice);
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
     * Facturis: save_client / saveClient.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function saveClient(array $data): array
    {
        return $this->brivio->createContact([
            'name' => $data['client_name'] ?? $data['nume'] ?? '',
            'vat_number' => $data['client_cui'] ?? $data['cui'] ?? null,
            'registration_number' => $data['client_rc'] ?? $data['nr_reg_com'] ?? null,
            'email' => $data['client_email'] ?? $data['email'] ?? null,
            'phone' => $data['client_telefon'] ?? $data['telefon'] ?? null,
            'address' => $data['client_adresa'] ?? $data['adresa'] ?? null,
            'city' => $data['client_oras'] ?? $data['oras'] ?? null,
            'county' => $data['client_judet'] ?? $data['judet'] ?? null,
        ]);
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
    private function mapInvoice(array $p): array
    {
        /** @var list<FacturisProduct> $products */
        $products = is_array($p['products'] ?? null) ? $p['products'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'name' => $row['denumire'] ?? $row['nume'] ?? '',
                'quantity' => (float) ($row['cantitate'] ?? 1),
                'unit_price' => (float) ($row['pret'] ?? 0),
                'vat_rate' => (float) ($row['cota_tva'] ?? 0),
                'vat_included' => (bool) ($row['tva_inclus'] ?? false),
                'unit' => $row['um'] ?? 'buc',
                'is_service' => ($row['tip'] ?? null) === 'serviciu',
            ];
        }, $products);

        return [
            'seller_vat_code' => $p['firma_cui'] ?? $this->cif,
            'client' => [
                'name' => $p['client_name'] ?? $p['client_nume'] ?? '',
                'vat_number' => $p['client_cui'] ?? null,
                'registration_number' => $p['client_rc'] ?? null,
                'email' => $p['client_email'] ?? null,
                'address' => $p['client_adresa'] ?? null,
                'city' => $p['client_oras'] ?? null,
                'county' => $p['client_judet'] ?? null,
                'save' => (bool) ($p['salveaza_client'] ?? false),
            ],
            'series_name' => $p['serie'] ?? null,
            'issue_date' => $p['data_factura'] ?? $p['date_invoice'] ?? null,
            'due_date' => $p['data_scadenta'] ?? null,
            'currency' => $p['moneda'] ?? 'RON',
            'lines' => $lines,
            'send_to_spv' => (bool) ($p['trimite_efactura'] ?? $p['e_factura'] ?? false),
        ];
    }
}
