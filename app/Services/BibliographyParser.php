<?php

namespace App\Services;

/**
 * Reads BibTeX and RIS exports (Scopus, Web of Science, IEEE, ACM, PubMed...) into study fields:
 * name, authors, year, doi, keywords, abstract, link, journal_conference, document_type.
 */
class BibliographyParser
{
    /**
     * Parse a file's contents in the given format ('bibtex' or 'ris').
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content, string $format): array
    {
        // Drop a UTF-8 BOM and normalize line endings
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return $format === 'ris' ? $this->parseRis($content) : $this->parseBibtex($content);
    }

    /**
     * Guess the format from the contents (for .txt exports), falling back to the extension.
     */
    public function detectFormat(string $content, string $extension): ?string
    {
        if (preg_match('/^\s*TY\s{1,2}-/m', $content)) {
            return 'ris';
        }
        if (preg_match('/@\s*[a-zA-Z]+\s*[{(]/', $content)) {
            return 'bibtex';
        }

        return match (strtolower($extension)) {
            'ris' => 'ris',
            'bib', 'bibtex' => 'bibtex',
            default => null,
        };
    }

    // ---------------------------------------------------------------- RIS

    private function parseRis(string $content): array
    {
        $records = [];
        $current = null;
        $lastTag = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9])\s{1,2}-\s?(.*)$/', $line, $m)) {
                $tag = $m[1];
                $value = trim($m[2]);

                if ($tag === 'TY') {
                    $current = ['TY' => [$value]];
                    $lastTag = 'TY';
                    continue;
                }
                if ($current === null) {
                    continue;
                }
                if ($tag === 'ER') {
                    $records[] = $current;
                    $current = null;
                    $lastTag = null;
                    continue;
                }

                $current[$tag][] = $value;
                $lastTag = $tag;
            } elseif ($current !== null && $lastTag !== null && trim($line) !== '') {
                // Continuation line (long abstracts are wrapped by some databases)
                $index = count($current[$lastTag]) - 1;
                $current[$lastTag][$index] .= ' ' . trim($line);
            }
        }

        // A last record without ER
        if ($current !== null) {
            $records[] = $current;
        }

        return array_values(array_filter(array_map(fn ($r) => $this->risToStudy($r), $records)));
    }

    private function risToStudy(array $r): ?array
    {
        $first = function (array $tags) use ($r): ?string {
            foreach ($tags as $tag) {
                foreach ($r[$tag] ?? [] as $value) {
                    if (trim($value) !== '') {
                        return trim($value);
                    }
                }
            }

            return null;
        };
        $all = function (array $tags) use ($r): array {
            $values = [];
            foreach ($tags as $tag) {
                foreach ($r[$tag] ?? [] as $value) {
                    if (trim($value) !== '') {
                        $values[] = trim($value);
                    }
                }
            }

            return $values;
        };

        $title = $first(['TI', 'T1', 'CT', 'BT']);
        if ($title === null) {
            return null;
        }

        // Keywords may come one per KW line or several in one line separated by ";"
        $keywords = [];
        foreach ($all(['KW', 'DE']) as $kw) {
            foreach (preg_split('/\s*;\s*/', $kw) as $part) {
                if ($part !== '') {
                    $keywords[] = $part;
                }
            }
        }

        return $this->study([
            'name' => $title,
            'authors' => implode('; ', $all(['AU', 'A1', 'A2'])) ?: null,
            'year' => $this->year($first(['PY', 'Y1', 'DA', 'Y2'])),
            'doi' => $first(['DO', 'DI']),
            'keywords' => $keywords ? implode('; ', array_unique($keywords)) : null,
            'abstract' => $first(['AB', 'N2']),
            'link' => $first(['UR', 'L2', 'L1']),
            'journal_conference' => $first(['T2', 'JO', 'JF', 'J2', 'JA', 'BT']),
            'document_type' => $this->risType($first(['TY'])),
        ]);
    }

    private function risType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return [
            'JOUR' => 'Journal Article',
            'JFULL' => 'Journal',
            'CONF' => 'Conference Paper',
            'CPAPER' => 'Conference Paper',
            'BOOK' => 'Book',
            'CHAP' => 'Book Chapter',
            'THES' => 'Thesis',
            'RPRT' => 'Report',
            'GEN' => 'Generic',
            'ELEC' => 'Web Page',
            'MGZN' => 'Magazine Article',
            'NEWS' => 'Newspaper Article',
            'PAT' => 'Patent',
            'STAND' => 'Standard',
            'UNPB' => 'Unpublished',
        ][strtoupper($type)] ?? $type;
    }

    // ------------------------------------------------------------- BibTeX

    private function parseBibtex(string $content): array
    {
        $studies = [];
        $length = strlen($content);
        $pos = 0;

        while (($at = strpos($content, '@', $pos)) !== false) {
            if (!preg_match('/\G@\s*([a-zA-Z]+)\s*([{(])/A', $content, $m, 0, $at)) {
                $pos = $at + 1;
                continue;
            }

            $type = strtolower($m[1]);
            $open = $m[2];
            $close = $open === '{' ? '}' : ')';
            $bodyStart = $at + strlen($m[0]);

            // Find the matching closing delimiter, honoring nested braces
            $depth = 1;
            $i = $bodyStart;
            while ($i < $length && $depth > 0) {
                $ch = $content[$i];
                if ($ch === '\\') {
                    $i += 2;
                    continue;
                }
                if ($ch === $open) {
                    $depth++;
                } elseif ($ch === $close) {
                    $depth--;
                }
                $i++;
            }

            $body = substr($content, $bodyStart, $i - $bodyStart - 1);
            $pos = $i;

            if (in_array($type, ['comment', 'string', 'preamble'], true)) {
                continue;
            }

            $fields = $this->bibtexFields($body);
            $study = $this->bibtexToStudy($type, $fields);
            if ($study !== null) {
                $studies[] = $study;
            }
        }

        return $studies;
    }

    /**
     * Read "key, field = {value}, field = "value", field = 2020" into [field => value].
     */
    private function bibtexFields(string $body): array
    {
        $fields = [];
        $length = strlen($body);

        // Skip the citation key
        $comma = strpos($body, ',');
        if ($comma === false) {
            return $fields;
        }
        $i = $comma + 1;

        while ($i < $length) {
            if (!preg_match('/\G[\s,]*([a-zA-Z0-9_\-:.+]+)\s*=\s*/A', $body, $m, 0, $i)) {
                break;
            }
            $name = strtolower($m[1]);
            $i += strlen($m[0]);

            $value = '';
            // A value may be several parts joined with "#"
            while ($i < $length) {
                $ch = $body[$i];
                if ($ch === '{') {
                    $depth = 1;
                    $start = ++$i;
                    while ($i < $length && $depth > 0) {
                        if ($body[$i] === '\\') {
                            $i += 2;
                            continue;
                        }
                        if ($body[$i] === '{') {
                            $depth++;
                        } elseif ($body[$i] === '}') {
                            $depth--;
                        }
                        $i++;
                    }
                    $value .= substr($body, $start, $i - $start - 1);
                } elseif ($ch === '"') {
                    $start = ++$i;
                    $depth = 0;
                    while ($i < $length && !($body[$i] === '"' && $depth === 0)) {
                        if ($body[$i] === '\\') {
                            $i += 2;
                            continue;
                        }
                        if ($body[$i] === '{') {
                            $depth++;
                        } elseif ($body[$i] === '}') {
                            $depth--;
                        }
                        $i++;
                    }
                    $value .= substr($body, $start, $i - $start);
                    $i++;
                } elseif (preg_match('/\G[^,#}\s]+/A', $body, $bare, 0, $i)) {
                    // Number or @string macro (macros are kept as text)
                    $value .= $bare[0];
                    $i += strlen($bare[0]);
                }

                if (preg_match('/\G\s*#\s*/A', $body, $hash, 0, $i)) {
                    $i += strlen($hash[0]);
                    continue;
                }
                break;
            }

            $fields[$name] = $this->cleanLatex($value);
        }

        return $fields;
    }

    private function bibtexToStudy(string $type, array $f): ?array
    {
        $title = $f['title'] ?? null;
        if ($title === null || $title === '') {
            return null;
        }

        $authors = null;
        if (!empty($f['author'])) {
            $authors = implode('; ', array_map('trim', preg_split('/\s+and\s+/i', $f['author'])));
        }

        $keywords = $f['keywords'] ?? $f['keyword'] ?? $f['author_keywords'] ?? null;
        if ($keywords !== null) {
            $keywords = implode('; ', array_filter(array_map('trim', preg_split('/\s*[;,]\s*/', $keywords))));
        }

        return $this->study([
            'name' => $title,
            'authors' => $authors,
            'year' => $this->year($f['year'] ?? $f['date'] ?? null),
            'doi' => $f['doi'] ?? null,
            'keywords' => $keywords ?: null,
            'abstract' => $f['abstract'] ?? null,
            'link' => $f['url'] ?? null,
            'journal_conference' => $f['journal'] ?? $f['journaltitle'] ?? $f['booktitle'] ?? $f['series'] ?? $f['publisher'] ?? null,
            'document_type' => $f['type'] ?? $f['document_type'] ?? $this->bibtexType($type),
        ]);
    }

    private function bibtexType(string $type): string
    {
        return [
            'article' => 'Journal Article',
            'inproceedings' => 'Conference Paper',
            'conference' => 'Conference Paper',
            'proceedings' => 'Proceedings',
            'book' => 'Book',
            'inbook' => 'Book Chapter',
            'incollection' => 'Book Chapter',
            'phdthesis' => 'Thesis',
            'mastersthesis' => 'Thesis',
            'techreport' => 'Report',
            'misc' => 'Misc',
            'online' => 'Web Page',
            'unpublished' => 'Unpublished',
        ][$type] ?? ucfirst($type);
    }

    /**
     * Turn common LaTeX into plain text: accents, escaped characters and grouping braces.
     */
    private function cleanLatex(string $value): string
    {
        $accents = [
            "'" => ['a' => 'á', 'e' => 'é', 'i' => 'í', 'o' => 'ó', 'u' => 'ú', 'y' => 'ý', 'c' => 'ć', 'n' => 'ń', 's' => 'ś', 'z' => 'ź',
                    'A' => 'Á', 'E' => 'É', 'I' => 'Í', 'O' => 'Ó', 'U' => 'Ú', 'Y' => 'Ý', 'C' => 'Ć', 'N' => 'Ń', 'S' => 'Ś', 'Z' => 'Ź'],
            '`' => ['a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù', 'A' => 'À', 'E' => 'È', 'I' => 'Ì', 'O' => 'Ò', 'U' => 'Ù'],
            '^' => ['a' => 'â', 'e' => 'ê', 'i' => 'î', 'o' => 'ô', 'u' => 'û', 'A' => 'Â', 'E' => 'Ê', 'I' => 'Î', 'O' => 'Ô', 'U' => 'Û'],
            '~' => ['a' => 'ã', 'o' => 'õ', 'n' => 'ñ', 'A' => 'Ã', 'O' => 'Õ', 'N' => 'Ñ'],
            '"' => ['a' => 'ä', 'e' => 'ë', 'i' => 'ï', 'o' => 'ö', 'u' => 'ü', 'y' => 'ÿ', 'A' => 'Ä', 'E' => 'Ë', 'I' => 'Ï', 'O' => 'Ö', 'U' => 'Ü'],
            'c' => ['c' => 'ç', 'C' => 'Ç', 's' => 'ş', 'S' => 'Ş'],
            'v' => ['c' => 'č', 's' => 'š', 'z' => 'ž', 'r' => 'ř', 'e' => 'ě', 'C' => 'Č', 'S' => 'Š', 'Z' => 'Ž', 'R' => 'Ř'],
        ];

        // \'{e}, {\'e}, \'e, \~{\i}, and \c{c}, \c c (letter commands need a brace or a space,
        // so \cite or \verb are not read as accents)
        $value = preg_replace_callback(
            '/\{?\\\\(?:([\'`^~"])\s*\{?|([cv])(?:\s*\{|\s+))\s*(\\\\i|[a-zA-Z])\s*\}?\}?/',
            function ($m) use ($accents) {
                $accent = $m[1] !== '' ? $m[1] : $m[2];
                $letter = $m[3] === '\\i' ? 'i' : $m[3];

                return $accents[$accent][$letter] ?? $letter;
            },
            $value
        );

        $value = strtr($value, [
            '\\&' => '&', '\\%' => '%', '\\$' => '$', '\\#' => '#', '\\_' => '_',
            '\\ss' => 'ß', '\\o' => 'ø', '\\O' => 'Ø', '\\ae' => 'æ', '\\AE' => 'Æ', '\\aa' => 'å', '\\AA' => 'Å',
            '\\l' => 'ł', '\\L' => 'Ł', '---' => '—', '--' => '–', '~' => ' ',
        ]);

        // Remaining commands like \textit{...} or \emph{...}: keep their text
        $value = preg_replace('/\\\\[a-zA-Z]+\s*/', '', $value);
        $value = str_replace(['{', '}'], '', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    // ------------------------------------------------------------- shared

    private function year(?string $value): ?int
    {
        if ($value !== null && preg_match('/(1[89]\d{2}|20\d{2}|2100)/', $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Trim values, drop empty ones, normalize the DOI and fit the studies table's column sizes.
     */
    private function study(array $data): array
    {
        $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);

        if ($data['doi'] !== null) {
            $data['doi'] = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:\s*)#i', '', $data['doi']);
        }

        foreach (['name', 'doi', 'link', 'journal_conference', 'document_type'] as $column) {
            if ($data[$column] !== null) {
                $data[$column] = mb_substr($data[$column], 0, 255);
            }
        }

        return $data;
    }
}
