<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs a search string on a database API (IEEE Xplore, Scopus, OpenAlex, PubMed) and returns
 * the results as study records (the same fields BibliographyParser reads from BibTeX/RIS).
 */
class DatabaseSearch
{
    /** Results per page of each API (Scopus COMPLETE view allows 25). */
    public const PAGE_SIZES = ['ieee' => 100, 'scopus' => 25, 'openalex' => 100, 'pubmed' => 100];

    /** Results the APIs can page through with plain paging. */
    public const MAX_RESULTS = ['ieee' => 10000, 'scopus' => 5000, 'openalex' => 10000, 'pubmed' => 9999];

    private const USER_AGENT = 'Mozilla/5.0 (compatible; LiteratureSearch/1.0)';

    /**
     * One page of results.
     *
     * @param array{ieee_api_key?: ?string, scopus_api_key?: ?string, scopus_insttoken?: ?string, pubmed_api_key?: ?string} $keys
     * @return array{total: int, page: int, per_page: int, has_more: bool, items: array<int, array>}
     *
     * @throws DatabaseSearchException when the API refuses the query or the key
     */
    public function search(string $source, string $query, int $page, array $keys = []): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new DatabaseSearchException('The search string is empty.');
        }

        $perPage = self::PAGE_SIZES[$source] ?? throw new DatabaseSearchException('Unknown database.');
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        if ($offset >= self::MAX_RESULTS[$source]) {
            throw new DatabaseSearchException('This API only pages through the first ' . self::MAX_RESULTS[$source] . ' results; narrow the search string.');
        }

        [$total, $items] = match ($source) {
            'ieee' => $this->ieee($query, $offset, $perPage, $keys['ieee_api_key'] ?? null),
            'scopus' => $this->scopus($query, $offset, $perPage, $keys['scopus_api_key'] ?? null, $keys['scopus_insttoken'] ?? null),
            'openalex' => $this->openAlex($query, $page, $perPage),
            'pubmed' => $this->pubMed($query, $offset, $perPage, $keys['pubmed_api_key'] ?? null),
        };

        $reachable = min($total, self::MAX_RESULTS[$source]);

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $offset + count($items) < $reachable && count($items) > 0,
            'items' => array_map(fn ($item) => $this->study($item), $items),
        ];
    }

    // ---------------------------------------------------------------- IEEE Xplore

    private function ieee(string $query, int $offset, int $perPage, ?string $key): array
    {
        if (!$key) {
            throw new DatabaseSearchException('IEEE Xplore needs an API key (developer.ieee.org).');
        }

        $response = $this->get('https://ieeexploreapi.ieee.org/api/v1/search/articles', [
            'apikey' => $key,
            'querytext' => $query,
            'start_record' => $offset + 1,
            'max_records' => $perPage,
            'format' => 'json',
        ]);
        if (!$response->successful()) {
            throw new DatabaseSearchException('IEEE Xplore: ' . $this->errorMessage($response, 'the key was refused or the query is invalid'));
        }

        $data = $response->json() ?? [];
        $items = [];
        foreach ($data['articles'] ?? [] as $a) {
            $terms = array_merge(
                $a['index_terms']['author_terms']['terms'] ?? [],
                $a['index_terms']['ieee_terms']['terms'] ?? []
            );
            $items[] = [
                'name' => $a['title'] ?? null,
                'authors' => implode('; ', array_column($a['authors']['authors'] ?? [], 'full_name')),
                'year' => $a['publication_year'] ?? null,
                'doi' => $a['doi'] ?? null,
                'keywords' => implode('; ', array_unique($terms)),
                'abstract' => $a['abstract'] ?? null,
                'link' => $a['html_url'] ?? (!empty($a['article_number']) ? 'https://ieeexplore.ieee.org/document/' . $a['article_number'] : null),
                'journal_conference' => $a['publication_title'] ?? null,
                'document_type' => $a['content_type'] ?? null,
            ];
        }

        return [(int) ($data['total_records'] ?? 0), $items];
    }

    // ---------------------------------------------------------------- Scopus

    private function scopus(string $query, int $offset, int $perPage, ?string $key, ?string $instToken): array
    {
        if (!$key) {
            throw new DatabaseSearchException('Scopus needs an API key (dev.elsevier.com) and institutional access.');
        }

        $headers = ['X-ELS-APIKey' => $key];
        if ($instToken) {
            $headers['X-ELS-Insttoken'] = $instToken;
        }
        $params = ['query' => $query, 'start' => $offset, 'count' => $perPage];

        // The COMPLETE view (abstracts, all authors, keywords) needs institutional entitlement;
        // without it Scopus answers 401/403 and the STANDARD view still lists the records
        $response = $this->get('https://api.elsevier.com/content/search/scopus', $params + ['view' => 'COMPLETE'], $headers);
        if (in_array($response->status(), [401, 403], true)) {
            $response = $this->get('https://api.elsevier.com/content/search/scopus', $params + ['view' => 'STANDARD'], $headers);
        }
        if (!$response->successful()) {
            throw new DatabaseSearchException('Scopus: ' . $this->errorMessage($response, 'the key was refused, there is no institutional access from this server, or the query is invalid'));
        }

        $results = $response->json('search-results') ?? [];
        $items = [];
        foreach ($results['entry'] ?? [] as $e) {
            if (isset($e['error'])) { // "Result set was empty"
                continue;
            }
            $authors = array_filter(array_map(fn ($a) => $a['authname'] ?? null, $e['author'] ?? []));
            $link = null;
            foreach ($e['link'] ?? [] as $l) {
                if (($l['@ref'] ?? null) === 'scopus') {
                    $link = $l['@href'] ?? null;
                }
            }
            $items[] = [
                'name' => $e['dc:title'] ?? null,
                'authors' => $authors ? implode('; ', array_unique($authors)) : ($e['dc:creator'] ?? null),
                'year' => isset($e['prism:coverDate']) ? (int) substr($e['prism:coverDate'], 0, 4) : null,
                'doi' => $e['prism:doi'] ?? null,
                'keywords' => isset($e['authkeywords']) ? implode('; ', array_map('trim', explode('|', $e['authkeywords']))) : null,
                'abstract' => $e['dc:description'] ?? null,
                'link' => $link,
                'journal_conference' => $e['prism:publicationName'] ?? null,
                'document_type' => $e['subtypeDescription'] ?? null,
            ];
        }

        return [(int) ($results['opensearch:totalResults'] ?? 0), $items];
    }

    // ---------------------------------------------------------------- OpenAlex

    private function openAlex(string $query, int $page, int $perPage): array
    {
        // Title and abstract search with AND/OR/NOT, quotes and parentheses; a comma would end
        // the filter value, so it becomes a space
        $response = $this->get('https://api.openalex.org/works', [
            'filter' => 'title_and_abstract.search:' . str_replace(',', ' ', $query),
            'per-page' => $perPage,
            'page' => $page,
        ]);
        if (!$response->successful()) {
            throw new DatabaseSearchException('OpenAlex: ' . $this->errorMessage($response, 'the query is invalid'));
        }

        $data = $response->json() ?? [];
        $items = [];
        foreach ($data['results'] ?? [] as $w) {
            $items[] = [
                'name' => $w['title'] ?? $w['display_name'] ?? null,
                'authors' => implode('; ', array_filter(array_map(fn ($a) => $a['author']['display_name'] ?? null, $w['authorships'] ?? []))),
                'year' => $w['publication_year'] ?? null,
                'doi' => $w['doi'] ?? null,
                'keywords' => implode('; ', array_filter(array_map(fn ($k) => $k['display_name'] ?? null, $w['keywords'] ?? []))),
                'abstract' => $this->invertedIndexToText($w['abstract_inverted_index'] ?? null),
                'link' => $w['primary_location']['landing_page_url'] ?? $w['doi'] ?? $w['id'] ?? null,
                'journal_conference' => $w['primary_location']['source']['display_name'] ?? null,
                'document_type' => $w['type'] ?? null,
            ];
        }

        return [(int) ($data['meta']['count'] ?? 0), $items];
    }

    /** OpenAlex ships abstracts as {word: [positions]}. */
    private function invertedIndexToText(?array $index): ?string
    {
        if (!$index) {
            return null;
        }
        $words = [];
        foreach ($index as $word => $positions) {
            foreach ($positions as $position) {
                $words[$position] = $word;
            }
        }
        ksort($words);

        return implode(' ', $words);
    }

    // ---------------------------------------------------------------- PubMed

    private function pubMed(string $query, int $offset, int $perPage, ?string $key): array
    {
        $auth = $key ? ['api_key' => $key] : [];

        $search = $this->get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi', [
            'db' => 'pubmed',
            'term' => $query,
            'retstart' => $offset,
            'retmax' => $perPage,
            'retmode' => 'json',
        ] + $auth);
        if (!$search->successful()) {
            throw new DatabaseSearchException('PubMed: ' . $this->errorMessage($search, 'the query is invalid'));
        }

        $result = $search->json('esearchresult') ?? [];
        if (!empty($result['ERROR'])) {
            throw new DatabaseSearchException('PubMed: ' . $result['ERROR']);
        }
        $ids = $result['idlist'] ?? [];
        if (empty($ids)) {
            return [(int) ($result['count'] ?? 0), []];
        }

        $fetch = Http::withUserAgent(self::USER_AGENT)->timeout(60)->asForm()
            ->post('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi', [
                'db' => 'pubmed',
                'id' => implode(',', $ids),
                'retmode' => 'xml',
            ] + $auth);
        if (!$fetch->successful()) {
            throw new DatabaseSearchException('PubMed: could not fetch the records (' . $fetch->status() . ').');
        }

        return [(int) ($result['count'] ?? 0), $this->parsePubMedXml($fetch->body())];
    }

    private function parsePubMedXml(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new DatabaseSearchException('PubMed: unreadable response.');
        }
        $xp = new \DOMXPath($dom);
        $text = fn (string $path, \DOMNode $node) => ($n = $xp->query($path, $node)->item(0)) ? trim(preg_replace('/\s+/', ' ', $n->textContent)) : null;

        $items = [];
        foreach ($xp->query('/PubmedArticleSet/PubmedArticle') as $article) {
            $authors = [];
            foreach ($xp->query('MedlineCitation/Article/AuthorList/Author', $article) as $author) {
                $name = $text('CollectiveName', $author)
                    ?? trim(($text('LastName', $author) ?? '') . ', ' . ($text('ForeName', $author) ?? $text('Initials', $author) ?? ''), ', ');
                if ($name) {
                    $authors[] = $name;
                }
            }

            $abstract = [];
            foreach ($xp->query('MedlineCitation/Article/Abstract/AbstractText', $article) as $part) {
                $label = $part instanceof \DOMElement ? $part->getAttribute('Label') : '';
                $abstract[] = ($label ? $label . ': ' : '') . trim(preg_replace('/\s+/', ' ', $part->textContent));
            }

            $keywords = [];
            foreach ($xp->query('MedlineCitation/KeywordList/Keyword', $article) as $keyword) {
                $keywords[] = trim($keyword->textContent);
            }

            $year = $text('MedlineCitation/Article/Journal/JournalIssue/PubDate/Year', $article)
                ?? $text('MedlineCitation/Article/Journal/JournalIssue/PubDate/MedlineDate', $article);
            $pmid = $text('MedlineCitation/PMID', $article);
            $types = [];
            foreach ($xp->query('MedlineCitation/Article/PublicationTypeList/PublicationType', $article) as $type) {
                $types[] = trim($type->textContent);
            }

            $items[] = [
                'name' => $text('MedlineCitation/Article/ArticleTitle', $article),
                'authors' => implode('; ', $authors),
                'year' => $year && preg_match('/\d{4}/', $year, $m) ? (int) $m[0] : null,
                'doi' => $text('PubmedData/ArticleIdList/ArticleId[@IdType="doi"]', $article),
                'keywords' => implode('; ', $keywords),
                'abstract' => implode("\n", $abstract),
                'link' => $pmid ? 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/' : null,
                'journal_conference' => $text('MedlineCitation/Article/Journal/Title', $article),
                'document_type' => $types[0] ?? null,
            ];
        }

        return $items;
    }

    // ---------------------------------------------------------------- helpers

    private function get(string $url, array $query, array $headers = []): \Illuminate\Http\Client\Response
    {
        try {
            return Http::withUserAgent(self::USER_AGENT)->withHeaders($headers)->acceptJson()->timeout(60)->get($url, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // The exception text holds the URL, which carries the IEEE key: keep it out
            throw new DatabaseSearchException('Could not reach ' . parse_url($url, PHP_URL_HOST) . ' (timeout or network error). Try again.');
        }
    }

    /** The API's own error text when it gives one. */
    private function errorMessage(\Illuminate\Http\Client\Response $response, string $fallback): string
    {
        $json = $response->json();
        $message = $json['service-error']['status']['statusText']
            ?? $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
        if (!is_string($message) || $message === '') {
            $message = $fallback;
        }

        return Str::limit(strip_tags($message), 300) . ' (HTTP ' . $response->status() . ')';
    }

    /**
     * Trim values, drop empty ones, strip markup, normalize the DOI and fit the studies table's
     * column sizes (as BibliographyParser does for BibTeX/RIS).
     */
    private function study(array $data): array
    {
        $data = array_map(function ($v) {
            if (!is_string($v)) {
                return $v;
            }
            $v = trim(html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5));

            return $v === '' ? null : $v;
        }, $data);

        if ($data['doi'] !== null) {
            $data['doi'] = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $data['doi']);
        }
        if ($data['year'] !== null) {
            $data['year'] = ($y = (int) $data['year']) >= 1900 && $y <= 2100 ? $y : null;
        }
        foreach (['name', 'doi', 'link', 'journal_conference', 'document_type'] as $column) {
            if ($data[$column] !== null) {
                $data[$column] = mb_substr($data[$column], 0, 255);
            }
        }

        return $data;
    }
}
