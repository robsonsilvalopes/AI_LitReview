<?php

namespace App\Services;

use App\Models\Study;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Finds a legal open-access PDF for a study's DOI and stores it next to the study PDFs.
 *
 * Sources: OpenAlex, Unpaywall (contact e-mail), Crossref (publisher full-text links),
 * Europe PMC, CORE (API key) and the DOI landing page. Grey literature is left out: the work
 * must be a peer-reviewed publication type (not a preprint, thesis, report, dataset...), each
 * indexed PDF must be its published or accepted version, and the sources that do not label
 * versions (Crossref links, CORE, the landing page) are only tried once OpenAlex or Unpaywall
 * confirmed that an open-access peer-reviewed version exists.
 */
class OpenAccessPdf
{
    private const MAX_BYTES = 52428800; // 50MB, same limit as manual uploads

    private const USER_AGENT = 'Mozilla/5.0 (compatible; OpenAccessPdfFetcher/1.0)';

    /** OpenAlex work types that are formally published, peer-reviewed literature. */
    private const OPENALEX_TYPES = ['article', 'review', 'book-chapter', 'book', 'letter'];

    /** Unpaywall and Crossref types that are formally published, peer-reviewed literature. */
    private const CROSSREF_TYPES = ['journal-article', 'proceedings-article', 'book-chapter', 'book', 'monograph', 'edited-book', 'book-part', 'book-section'];

    /** Crossref types that are grey literature: a DOI of one of these is never downloaded. */
    private const CROSSREF_GREY_TYPES = ['posted-content', 'dissertation', 'report', 'report-component', 'report-series', 'dataset', 'standard', 'peer-review', 'other'];

    /** CORE document types that are grey literature. */
    private const CORE_GREY_TYPES = ['thesis', 'preprint', 'report', 'dissertation', 'presentation', 'working paper', 'poster'];

    /** PDF versions that went through peer review (OpenAlex/Unpaywall, Crossref). */
    private const PEER_REVIEWED_VERSIONS = ['publishedVersion', 'acceptedVersion'];

    private const CROSSREF_VERSIONS = ['vor', 'am'];

    /** URL the last downloaded PDF actually came from (a landing page's citation_pdf_url). */
    private ?string $lastPdfUrl = null;

    /**
     * The bare DOI ("10.xxxx/yyy") of a DOI field, or null when the field holds no DOI.
     */
    public static function normalizeDoi(?string $doi): ?string
    {
        $doi = trim((string) $doi);
        $doi = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $doi);

        return preg_match('#^10\.\d{4,9}/\S+$#', $doi) ? $doi : null;
    }

    /**
     * Download an open-access PDF for the study's DOI and store it.
     *
     * @param string|null $email   contact e-mail for Unpaywall and Crossref (null: UNPAYWALL_EMAIL)
     * @param string|null $coreKey CORE API key (null: CORE_API_KEY)
     * @return array{path: ?string, url: ?string, via: ?string, size: ?int, tried: int, reason: ?string, links: array}
     *               path/url/source of the stored PDF, or the reason no PDF was stored and the
     *               open-access links found, to open in a browser when the download was blocked
     */
    public function fetch(Study $study, ?string $email = null, ?string $coreKey = null): array
    {
        $result = ['path' => null, 'url' => null, 'via' => null, 'size' => null, 'tried' => 0, 'reason' => null, 'links' => []];

        $doi = self::normalizeDoi($study->doi);
        if ($doi === null) {
            return ['reason' => 'Invalid DOI'] + $result;
        }

        $candidates = $this->candidates($doi, $email, $coreKey);
        if (empty($candidates)) {
            Log::info('OpenAccessPdf: no peer-reviewed open-access version', ['study_id' => $study->id, 'doi' => $doi]);

            return ['reason' => 'No open-access peer-reviewed version (closed access or grey literature)'] + $result;
        }

        foreach ($candidates as $candidate) {
            $result['tried']++;
            $this->lastPdfUrl = null;
            $pdf = $this->download($candidate['url'], $candidate['headers']);
            if ($pdf === null) {
                continue;
            }

            $path = 'studies/' . $study->project_id . '/' . time() . '_' . uniqid() . '_'
                . Str::limit(Str::slug($doi), 100, '') . '.pdf';
            Storage::disk()->put($path, $pdf, 'public');

            Log::info('OpenAccessPdf: PDF downloaded', [
                'study_id' => $study->id,
                'doi' => $doi,
                'via' => $candidate['via'],
                'url' => $this->lastPdfUrl ?? $candidate['url'],
                'path' => $path,
            ]);

            return [
                'path' => $path,
                'url' => $this->lastPdfUrl ?? $candidate['url'],
                'via' => $candidate['via'],
                'size' => strlen($pdf),
            ] + $result;
        }

        Log::info('OpenAccessPdf: no PDF could be downloaded', [
            'study_id' => $study->id,
            'doi' => $doi,
            'urls' => array_column($candidates, 'url'),
        ]);

        return [
            'reason' => 'Download blocked or not a PDF (' . $result['tried'] . ' link(s) tried); open a link below in your browser and upload the PDF',
            'links' => $this->browserLinks($candidates),
        ] + $result;
    }

    /**
     * PDF URLs for the DOI, best first, without repeats.
     *
     * @return array<int, array{url: string, via: string, headers: array}>
     */
    public function candidates(string $doi, ?string $email = null, ?string $coreKey = null): array
    {
        $email = $email ?: config('services.pdf_download.unpaywall_email');
        $coreKey = $coreKey ?: config('services.pdf_download.core_api_key');

        $crossref = $this->crossrefWork($doi, $email);
        if ($crossref && in_array($crossref['type'] ?? null, self::CROSSREF_GREY_TYPES, true)) {
            return [];
        }

        [$openAlexUrls, $openAlexOk] = $this->fromOpenAlex($doi);
        [$unpaywallUrls, $unpaywallOk] = $this->fromUnpaywall($doi, $email);
        if (!$openAlexOk && !$unpaywallOk) {
            return [];
        }

        $candidates = array_merge(
            $this->tag($openAlexUrls, 'OpenAlex'),
            $this->tag($unpaywallUrls, 'Unpaywall'),
            $this->tag($this->fromCrossref($crossref), 'Crossref'),
            $this->fromCore($doi, $coreKey),
            // Last resort: the DOI's landing page is the version of record; download() follows
            // its citation_pdf_url meta tag (works for publishers whose PDF links are not indexed)
            $this->tag(['https://doi.org/' . $doi], 'DOI page')
        );

        $unique = [];
        foreach ($candidates as $candidate) {
            $unique[$candidate['url']] ??= $candidate;
        }

        return array_values($unique);
    }

    /**
     * The candidates as links a browser can open: CORE API downloads need the key header,
     * so they become CORE's public download URL.
     *
     * @return array<int, array{url: string, via: string}>
     */
    private function browserLinks(array $candidates): array
    {
        $links = [];
        foreach ($candidates as $candidate) {
            $url = preg_replace('#^https://api\.core\.ac\.uk/v3/outputs/(\d+)/download$#', 'https://core.ac.uk/download/$1.pdf', $candidate['url']);
            $links[$url] ??= ['url' => $url, 'via' => $candidate['via']];
        }

        return array_values($links);
    }

    /** @return array<int, array{url: string, via: string, headers: array}> */
    private function tag(array $urls, string $via): array
    {
        return array_map(
            fn ($url) => ['url' => $url, 'via' => $via, 'headers' => []],
            array_values(array_filter($urls))
        );
    }

    /**
     * Peer-reviewed PDF URLs from OpenAlex (plus Europe PMC for PubMed Central works).
     *
     * @return array{0: string[], 1: bool} the URLs, and whether OpenAlex knows the DOI as
     *                                     open-access, peer-reviewed literature
     */
    private function fromOpenAlex(string $doi): array
    {
        $work = $this->getJson('https://api.openalex.org/works/doi:' . $doi);
        if (!$work || !in_array($work['type'] ?? null, self::OPENALEX_TYPES, true) || empty($work['open_access']['is_oa'])) {
            return [[], false];
        }

        $locations = array_merge([$work['best_oa_location'] ?? null], $work['locations'] ?? []);
        $urls = array_map(
            fn ($location) => $location['pdf_url'] ?? null,
            array_filter($locations, fn ($location) => $this->isPeerReviewedLocation($location))
        );

        // PubMed Central PDFs sit behind a bot challenge; Europe PMC serves the same file
        $pmcid = basename((string) ($work['ids']['pmcid'] ?? ''));
        if (preg_match('#^(PMC)?\d+$#i', $pmcid)) {
            $urls[] = 'https://europepmc.org/articles/PMC' . preg_replace('#^PMC#i', '', $pmcid) . '?pdf=render';
        }

        $hasPeerReviewedOa = !empty(array_filter($locations, fn ($location) => $this->isPeerReviewedLocation($location)));

        return [$urls, $hasPeerReviewedOa];
    }

    /**
     * Peer-reviewed PDF URLs from Unpaywall, which requires a contact e-mail.
     *
     * @return array{0: string[], 1: bool} the URLs, and whether Unpaywall lists an
     *                                     open-access peer-reviewed version
     */
    private function fromUnpaywall(string $doi, ?string $email): array
    {
        if (!$email) {
            return [[], false];
        }

        $record = $this->getJson('https://api.unpaywall.org/v2/' . $doi, ['email' => $email]);
        if (!$record || !in_array($record['genre'] ?? null, self::CROSSREF_TYPES, true)) {
            return [[], false];
        }

        $locations = array_filter(
            array_merge([$record['best_oa_location'] ?? null], $record['oa_locations'] ?? []),
            fn ($location) => $this->isPeerReviewedLocation($location)
        );

        return [array_map(fn ($location) => $location['url_for_pdf'] ?? null, $locations), !empty($locations)];
    }

    /** The Crossref record of the DOI (null when Crossref does not know it, e.g. DataCite DOIs). */
    private function crossrefWork(string $doi, ?string $email): ?array
    {
        $record = $this->getJson('https://api.crossref.org/works/' . $doi, $email ? ['mailto' => $email] : []);

        return $record['message'] ?? null;
    }

    /**
     * Publisher full-text PDF links of the version of record or accepted manuscript.
     *
     * @return string[]
     */
    private function fromCrossref(?array $work): array
    {
        if (!$work || !in_array($work['type'] ?? null, self::CROSSREF_TYPES, true)) {
            return [];
        }

        $urls = [];
        foreach ($work['link'] ?? [] as $link) {
            if (($link['content-type'] ?? null) === 'application/pdf'
                && in_array($link['content-version'] ?? null, self::CROSSREF_VERSIONS, true)) {
                $urls[] = $link['URL'] ?? null;
            }
        }

        return $urls;
    }

    /**
     * Repository copies from CORE whose DOI is the study's, skipping grey literature document
     * types. CORE only serves its files to API key holders.
     *
     * @return array<int, array{url: string, via: string, headers: array}>
     */
    private function fromCore(string $doi, ?string $key): array
    {
        if (!$key) {
            return [];
        }

        $headers = ['Authorization' => 'Bearer ' . $key];
        $results = $this->getJson('https://api.core.ac.uk/v3/search/works', ['q' => 'doi:"' . $doi . '"', 'limit' => 10], $headers);

        $candidates = [];
        foreach ($results['results'] ?? [] as $work) {
            if (strcasecmp((string) ($work['doi'] ?? ''), $doi) !== 0) {
                continue;
            }
            if (in_array(strtolower((string) ($work['documentType'] ?? '')), self::CORE_GREY_TYPES, true)) {
                continue;
            }
            foreach ($work['outputs'] ?? [] as $output) {
                if (preg_match('#/outputs/(\d+)$#', $output, $m)) {
                    $candidates[] = ['url' => 'https://api.core.ac.uk/v3/outputs/' . $m[1] . '/download', 'via' => 'CORE', 'headers' => $headers];
                }
            }
            if (!empty($work['downloadUrl'])) {
                $candidates[] = ['url' => $work['downloadUrl'], 'via' => 'CORE', 'headers' => $headers];
            }
        }

        return array_slice($candidates, 0, 5);
    }

    private function isPeerReviewedLocation(?array $location): bool
    {
        return $location !== null
            && in_array($location['version'] ?? null, self::PEER_REVIEWED_VERSIONS, true);
    }

    private function getJson(string $url, array $query = [], array $headers = []): ?array
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)->withHeaders($headers)->acceptJson()->timeout(20)->get($url, $query);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::warning('OpenAccessPdf: lookup failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The file at the URL when it is a PDF of acceptable size; publishers often answer
     * with an HTML landing page or a login wall instead, which is skipped.
     */
    private function download(string $url, array $headers = [], bool $followLandingPage = true): ?string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->withHeaders(['Accept' => 'application/pdf,*/*;q=0.8'] + $headers)
                ->timeout((int) config('services.pdf_download.timeout', 60))
                ->get($url);
        } catch (\Exception $e) {
            Log::info('OpenAccessPdf: download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        $body = $response->body();
        if (!$response->successful()) {
            return null;
        }
        if (str_starts_with($body, '%PDF')) {
            if (strlen($body) > self::MAX_BYTES) {
                return null;
            }
            $this->lastPdfUrl = $url;

            return $body;
        }

        // An article landing page names its PDF in the citation_pdf_url meta tag
        if ($followLandingPage && preg_match('#<meta[^>]+name=["\']citation_pdf_url["\'][^>]+content=["\']([^"\']+)["\']#i', $body, $m)) {
            return $this->download(html_entity_decode($m[1]), [], false);
        }

        return null;
    }
}
