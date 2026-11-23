<?php

namespace App\Services;

/**
 * Example structures for the prompt template form, one per template type. The screening examples
 * use the standard 1-7 Likert scale and the {"criteria": [...], "overall": ...} answer that
 * AiScreeningCriteria reads (a criterion applies with score >= 6); the extraction example uses
 * [[jsondata]] and the "_evidence" answer. Text in [BRACKETS] is for the researcher to replace.
 */
class TemplateExamples
{
    public const LIKERT_SCALE = '1 = strongly disagree, 2 = disagree, 3 = somewhat disagree, 4 = neutral / cannot be determined from %s, 5 = somewhat agree, 6 = agree, 7 = strongly agree.';

    public static function all(): array
    {
        return [
            'initial' => self::initial(),
            'final' => self::final(),
            'extraction' => self::extraction(),
        ];
    }

    public static function initial(): string
    {
        $scale = sprintf(self::LIKERT_SCALE, 'title and abstract');

        return <<<TXT
Assume you are a researcher in [YOUR RESEARCH AREA], conducting a [systematic literature review / systematic mapping study] on [REVIEW TOPIC]. You are screening a candidate study using ONLY its title and abstract.

[[pico]]

Title: [[title]]
Abstract: [[abstract]]

Rate your agreement (1-7 Likert scale) that the study satisfies each criterion below:
{$scale}

Inclusion criteria (a high score means the study meets the criterion):
[[inclusioncriteria]]

Exclusion criteria (a high score means the exclusion criterion applies to the study):
[[exclusioncriteria]]

Screening rules:
- Base your judgment only on the title and abstract. Do not assume information that is not stated.
- Criteria that cannot be assessed from the title and abstract (e.g., full-text accessibility, older versions of the same paper) must receive score 4.
- [DEFINE THE KEY TERMS OF THE REVIEW, e.g., which methods, populations or contexts count and which do not.]
- Reviews, surveys, editorials and other non-primary studies meet the corresponding exclusion criterion.
- Write a short justification (one or two sentences) for every score, citing the evidence from the title or abstract.

Overall decision:
- "exclude" only if at least one exclusion criterion clearly applies (score 6 or 7) or the inclusion criterion is clearly not met (score 1 or 2).
- Otherwise "include". When in doubt, choose "include": the study will be checked again in the full-text stage.

Return ONLY valid JSON, with no text before or after it, in this format:
{
  "criteria": [
    { "id": "IC1", "score": 6, "justification": "..." },
    { "id": "EC1", "score": 4, "justification": "..." }
  ],
  "overall": "include"
}
Include one entry in "criteria" for every inclusion and exclusion criterion listed above, using their IDs.
TXT;
    }

    public static function final(): string
    {
        $scale = sprintf(self::LIKERT_SCALE, 'the full text');

        return <<<TXT
Assume you are a researcher in [YOUR RESEARCH AREA], conducting a [systematic literature review / systematic mapping study] on [REVIEW TOPIC]. You are performing the final (full-text) screening of a candidate study. Read the full text below carefully, including the methods, data and results sections.

[[pico]]

Study full text:
[[fullstudy]]

Rate your agreement (1-7 Likert scale) that the study satisfies each criterion below:
{$scale}

Inclusion criteria (a high score means the study meets the criterion):
[[inclusioncriteria]]

Exclusion criteria (a high score means the exclusion criterion applies to the study):
[[exclusioncriteria]]

Screening rules:
- Base your judgment only on the full text provided. Do not assume information that is not stated.
- Full-text accessibility: since the full text is provided, that criterion does not apply (score 1), unless the text is truncated, unreadable or contains only the abstract or front matter (then score 6 and say so).
- Retracted papers: if the text is marked as retracted, state it in the justification and set "overall" to "exclude".
- [DEFINE THE KEY TERMS OF THE REVIEW, e.g., what must be a central part of the work (not only mentioned in the related work), and which populations, data or contexts count.]
- Reviews, surveys, editorials, letters, and theses or book chapters that are not peer-reviewed primary research meet the corresponding exclusion criteria.
- Write a short justification (one or two sentences) for every score, citing the specific evidence from the text (e.g., section, dataset, method, results).

Overall decision:
- "exclude" if at least one exclusion criterion clearly applies (score 6 or 7) or the inclusion criterion is not met (score 1, 2 or 3).
- "include" only if the inclusion criterion is met (score 5, 6 or 7) and no exclusion criterion applies (all scores 4 or lower).
- In any other case, choose "include" and start the inclusion criterion justification with "UNCERTAIN:", so that a human reviewer checks it.

Return ONLY valid JSON, with no text before or after it, in this format:
{
  "criteria": [
    { "id": "IC1", "score": 7, "justification": "..." },
    { "id": "EC1", "score": 1, "justification": "..." }
  ],
  "overall": "include"
}
Include one entry in "criteria" for every inclusion and exclusion criterion listed above, using their IDs.
TXT;
    }

    public static function extraction(): string
    {
        return <<<'TXT'
Assume you are a researcher in [YOUR RESEARCH AREA], conducting a [systematic literature review / systematic mapping study] on [REVIEW TOPIC] ([PERIOD, e.g., 2010 to 2026]). The study below has already been included. Extract the data extraction fields listed below from its full text.

[[pico]]

Data extraction fields:
[[jsondata]]

Study full text:
[[fullstudy]]

Extraction rules:
- Use only information stated in the full text. Do not infer from general knowledge unless the text states it explicitly.
- If a field is not reported, use "Not reported". If it does not apply to the study, use "Not applicable". Never leave a field empty and never invent values.
- When a field has more than one value, separate them with "; ".
- Write values in English, concisely (a short phrase or a list, not long sentences), and keep numbers as reported.
- [LIST CONTROLLED VOCABULARIES FOR THE FIELDS THAT NEED THEM, e.g., "Research type: validation research; evaluation research; solution proposal; ..."]
- For each field, prefer the values of the main proposed approach as reported by the authors.
Return ONLY valid JSON, with no text before or after it: a single object with one key per data extraction field, using the exact field IDs listed above as keys (e.g. "ED1", "ED2"), and the extracted value as a string, plus an "_evidence" key.
In "_evidence", for each field ID, list the passages of the study text that support the value, copied word for word from the full text (exact sentences, no paraphrasing, no ellipsis inside a passage), at most 5 short passages per field. Use an empty list when the value is "Not reported" or "Not applicable".
Example (the values and passages are illustrative):
{
  "ED1": "2022",
  "ED2": "Journal article",
  "ED3": "Not reported",
  "_evidence": {
    "ED1": ["Received 12 January 2022; accepted 3 March 2022"],
    "ED2": [],
    "ED3": []
  }
}
TXT;
    }
}
