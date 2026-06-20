<?php

declare(strict_types=1);

namespace Brivio\Legacy;

use Brivio\BrivioClient;

/**
 * Drop-in compatibility shim for code written against the FGO API
 * (testapp.fgo.ro/publicws). Mirrors FGO's RO field names (Client, Continut,
 * Serie, TipFactura) and maps them to Brivio's canonical `/v1` invoice API.
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
        return $this->brivio->createInvoice($this->mapInvoice($payload));
    }

    /**
     * FGO clients are created implicitly via the invoice `Client` block; expose
     * a direct mapping too for code that pre-creates contacts.
     *
     * @param array<string,mixed> $fgoClient
     * @return array<string,mixed>
     */
    public function createClient(array $fgoClient): array
    {
        return $this->brivio->createContact($this->mapClient($fgoClient));
    }

    /**
     * @param array<string,mixed> $client
     * @return array<string,mixed>
     */
    private function mapClient(array $client): array
    {
        $tip = strtoupper((string) ($client['Tip'] ?? 'PJ'));
        return [
            'name' => $client['Denumire'] ?? '',
            'person_type' => $tip === 'PF' ? 'INDIVIDUAL' : 'COMPANY',
            'vat_number' => $client['CodUnic'] ?? null,
            'registration_number' => $client['NrRegCom'] ?? null,
            'email' => $client['Email'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function mapInvoice(array $p): array
    {
        /** @var array<string,mixed> $client */
        $client = is_array($p['Client'] ?? null) ? $p['Client'] : [];
        /** @var list<FgoLine> $continut */
        $continut = is_array($p['Continut'] ?? null) ? $p['Continut'] : [];

        $lines = array_map(static function (array $row): array {
            return [
                'name' => $row['Denumire'] ?? '',
                'quantity' => (float) ($row['NrProduse'] ?? 1),
                'unit_price' => (float) ($row['PretUnitar'] ?? 0),
                'vat_rate' => (float) ($row['CotaTVA'] ?? 0),
                'unit' => $row['UM'] ?? 'buc',
            ];
        }, $continut);

        return [
            'client' => $this->mapClient($client),
            'series_name' => $p['Serie'] ?? null,
            'currency' => $p['Valuta'] ?? 'RON',
            'lines' => $lines,
        ];
    }
}
