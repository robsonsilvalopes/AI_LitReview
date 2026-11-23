# AI LitReview

**AI LitReview** is a collaborative, web-based tool for **AI-assisted Systematic Literature Reviews (SLRs)** in Software Engineering and related fields. It supports the full SLR workflow—from **protocol definition** through **study import**, **duplicate removal**, **initial and final study selection**, **structured data extraction**, and **RAG-based report generation**—with optional Large Language Models (LLMs) via the [OpenRouter](https://openrouter.ai) API (365+ models) or **local inference** via [Ollama](https://ollama.com).

**Live demo:** [https://ailitreview.dzp.net.br](https://ailitreview.dzp.net.br)

## Table of contents

- [SLR workflow and phases](#slr-workflow-and-phases)
- [Key features](#key-features)
- [What's new](#whats-new)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation with Docker (recommended)](#installation-with-docker-recommended)
- [Manual installation](#manual-installation)
- [Configuration](#configuration)
- [Using the platform](#using-the-platform)
- [Prompt templates](#prompt-templates)
- [Queues and workers](#queues-and-workers)
- [Production deployment (Coolify)](#production-deployment-coolify)
- [Useful commands](#useful-commands)
- [Additional documentation](#additional-documentation)
- [License](#license)

## SLR workflow and phases

AI LitReview follows the established SLR process (planning → execution → reporting). In the tool, work is organized into **projects** (one SLR per project) with **multi-user collaboration** and per-project data isolation.

| Phase | Activities in AI LitReview |
|-------|---------------------------|
| **Planning** | Protocol definition: research questions (RQs), search strings, inclusion criteria (ICs), exclusion criteria (ECs), and the data extraction form (extraction fields). |
| **Execution** | Study import (Excel/CSV, BibTeX/RIS, manual entry, bulk PDF upload), duplicate removal, **initial selection** (title/abstract), **final selection** (full-text PDF), and **data extraction**. |
| **Reporting** | Study Flow Diagram (Sankey chart), answers to RQs via **Retrieval-Augmented Generation (RAG)** over included PDFs, and evidence export (including **XLS export** per phase). |

Searches in bibliographic databases are performed **outside** the tool; candidate studies are then imported into the project.

## Key features

| Feature | Description |
|---------|-------------|
| **Protocol & project management** | Per-SLR protocol; invite collaborators by email; role-based access. |
| **Study corpus** | Import references (Excel/CSV, BibTeX, RIS) linked to the database they came from; bulk PDF upload with automatic assignment when filenames match study IDs (e.g. `S1.pdf`). |
| **Duplicate removal** | Resolve duplicates before selection. |
| **Initial selection** | Title/abstract screening; unlimited **screenings** per activity (Human, AI OpenRouter, or AI Local). |
| **Final selection** | Full-text screening using the Markdown version of each study's PDF. |
| **Data extraction** | Configurable extraction fields; human and AI extraction with structured JSON outputs; the passages each AI answer came from are highlighted in the article, one color per field. |
| **Prompt templates** | Per-project, per-activity templates with dynamic variables (see [Prompt templates](#prompt-templates)). |
| **Human-in-the-loop** | Compare multiple LLM outputs (e.g. Likert scores per criterion) before include/exclude decisions. |
| **Transparency** | Per-study **token usage**, **processing time**, and **cost (USD)** for API-based screenings. |
| **Report & auditability** | RAG-based RQ synthesis, Sankey Study Flow Diagram, XLS export at each phase. |
| **LLM providers** | **OpenRouter** (cloud, pay-as-you-go) and **Ollama** (local, privacy-friendly). |

## What's new

### Import from BibTeX and RIS, linked to the source database

- **Studies** and the project page have an **Import BibTeX/RIS** button (`.bib`, `.ris`, or a `.txt` export with either content).
- The import asks for the **database** the file was exported from, chosen among the databases registered with the project's **search strings**. Each imported study is linked to that search string (`studies.search_string_id`), and its `source` is set to the database name.
- Title, authors, year, DOI, keywords, abstract, URL, journal/conference, and document type are read from each reference; LaTeX accents (`{\~a}`, `\c{c}`) become plain text.
- The **Studies** table can be **filtered by database**: registered databases, the free-text source of manual and Excel entries, or *No database*.

### Review steps menu

The main pages of a project (project, Studies, Duplicates, Initial and Final selection, Extraction, Report, PRISMA 2020) have a floating **Review steps** menu on the right (`app/Services/ProjectSteps.php`), with a check icon for each step done and a warning icon for each step missing, plus a short status (e.g. *3 studies without a decision*):

- **Planning**: project information (description and objective), question framework (chosen, every element described or not applicable), research questions, inclusion criteria, exclusion criteria, search strings. A click scrolls to the section of the project page (or opens the project page there).
- **Conducting**: search and import (the project has studies), duplicate removal, initial and final selection (every study has a decision), data extraction (at least one extraction run). A click opens the phase page, and the step of the current page is highlighted.
- **Reporting**: report (every research question answered) and PRISMA 2020 (all checklist items answered).

The phase rules are the same as the progress cards of the project page; both use `app/Services/ProjectStatistics.php`, which counts the phases in the database. On wide screens the menu sits next to the content and can be hidden (remembered in the browser); on narrow screens a **Steps** button opens it. The status is computed when the page loads.

### Research question framework (PICO, PICOC, PCC, SPIDER...)

The project page has a **Research Question Framework** card (owner edits, collaborators view):

| Framework | Elements | Typical use |
|-----------|----------|-------------|
| PICO | Population/Problem, Intervention, Comparison, Outcome | Health, interventions |
| PICOS / PICOT | PICO + Study design / Time | Health |
| PICOC | Population, Intervention, Comparison, Outcome, Context | Software engineering (Kitchenham) |
| PCC | Population, Concept, Context | Scoping reviews (JBI) |
| SPIDER | Sample, Phenomenon of Interest, Design, Evaluation, Research type | Qualitative and mixed-methods studies |
| PEO | Population, Exposure, Outcome | Observational studies |
| Custom | Your own elements | Other cases |

- Each element has a description, keywords and synonyms (one per line), **Not applicable**, and **Use in search** (by default the population and intervention/concept/phenomenon).
- **Generate search string draft** joins each searched element's keywords with OR and the blocks with AND, in generic, Scopus (`TITLE-ABS-KEY`), IEEE Xplore, PubMed (`[tiab]`) and OpenAlex syntax; **Use as new search string** opens the search string form with the draft and the API database filled in. A draft is a starting point to refine in each database.
- Prompt templates can use `[[pico]]`: the framework with each element's description and keywords (empty when the project has none). The example structures of the template form include it.
- PRISMA checklist items 4 and 5 show the framework and its elements under **Recorded in the tool**.
- Stored in `projects.question_framework` and `projects.question_framework_elements`.

### PRISMA 2020 flow diagram and checklist

**Report → PRISMA 2020** (also linked from the project's Study Flow Diagram) has two tabs:

- **Flow diagram**: the PRISMA 2020 diagram for new systematic reviews (databases and registers), computed from the project:
  - records identified per database (the search string's database, else the study's source);
  - duplicates removed, records screened and excluded in the initial selection;
  - reports sought (included in the initial selection), not retrieved (no PDF and no final decision), assessed, and excluded with reasons (the exclusion criteria marked in the final selection);
  - studies included.

  Warnings list the studies still without a decision in a phase. **Adjust numbers** (owner) overrides any box for work done outside the tool (registers, automation tools, other removals...) and can replace the reasons; derived totals follow the adjustments. Download as **SVG** (editable in Word, Inkscape, draw.io) or **PNG**.
- **Checklist**: the 42 items of the PRISMA 2020 checklist, each with a status (reported, not applicable, not reported yet), the location in the manuscript, and notes (saved per project; owner edits, collaborators view). **Recorded in the tool** shows what the project already documents for items 4 (objectives and research questions), 5 (eligibility criteria), 6 (information sources and import dates), 7 (search strings), 8 and 9 (human or AI screening and extraction, with model and prompt template), 10a, 16a (flow numbers), 16b (exclusion reasons), and 17. **Export XLSX** gives the filled checklist for submission.

The flow adjustments and the checklist answers are stored in `projects.prisma_flow` and `projects.prisma_checklist`.

### API keys and contact e-mail in Settings

**Settings** (`/settings`) has a **Literature databases & open-access PDFs** card with every value the database search and the open-access PDF search use:

| Field | Used by | Needed |
|-------|---------|--------|
| Contact e-mail | Find open-access PDFs (Unpaywall, Crossref) | Optional; without it Unpaywall is skipped |
| IEEE Xplore API key | Search databases | Required for IEEE Xplore |
| Scopus API key | Search databases | Required for Scopus |
| Scopus institutional token | Search databases | Optional; for Scopus outside the institution network |
| NCBI (PubMed) API key | Search databases | Optional; raises the PubMed rate limit |
| CORE API key | Find open-access PDFs | Optional; without it CORE is skipped |

Keys are saved encrypted (`users.*_api_key`, `users.scopus_insttoken`) and never sent back to the page, which only shows whether one is saved. An empty field keeps the saved key, and **Remove the saved key** deletes it. The Search databases and Find open-access PDFs windows read and save the same values.

### Run search strings on database APIs (IEEE Xplore, Scopus, OpenAlex, PubMed)

- A search string can be linked to an **API database** (field on the search string form of the project page; `search_strings.api_source`). Write the string in that database's own syntax.
- **Studies → Search databases** (project owner) opens a window: choose the database and one of its search strings, adjust the query if needed, and run it. Results come with title, authors, year, DOI, abstract, keywords, venue, and document type; **Load more** fetches the next page and **Load all** fetches up to 2,000 results.
- Select one, several, or all results (**Select all not in project** skips studies already in the project, matched by DOI or title) and **Add selected**. The new studies get the next `S<n>` IDs, are linked to the search string (so the database filter and the report count them), and have `registration_method` set to e.g. `Scopus API`.
- Keys are typed in the window and saved encrypted to the user's account (`users.ieee_api_key`, `scopus_api_key`, `scopus_insttoken`, `pubmed_api_key`); the page only learns whether one is saved.

| Database | Key | Notes |
|----------|-----|-------|
| IEEE Xplore | Required, free from [developer.ieee.org](https://developer.ieee.org/) | Daily call limit; 100 results per page |
| Scopus | Required, from [dev.elsevier.com](https://dev.elsevier.com/) | Needs institutional access: the server on the institution network, or an institutional token. Without the entitlement for the COMPLETE view, results come without abstracts and with the first author only. 25 results per page, first 5,000 results |
| OpenAlex | None | Searches titles and abstracts; returns every work type, including preprints |
| PubMed | Optional NCBI key (raises the rate limit) | First 9,999 results |

### Full text as Markdown (pymupdf4llm)

- When a study gets or replaces a PDF (add, edit, bulk upload, or assign), a background job (`GenerateStudyMarkdown`) converts it to Markdown with [pymupdf4llm](https://pypi.org/project/pymupdf4llm/) and stores the `.md` next to the PDF (`studies.md_path`). Replacing the PDF or deleting the study removes the old Markdown.
- **Final selection** and **data extraction** (OpenRouter and Ollama) send this Markdown as `[[fullstudy]]`, replacing `pdftotext`. A study without Markdown yet is converted the first time the AI needs it.
- For PDFs uploaded before this feature, queue the conversion with `php artisan studies:generate-markdown` (optionally `--project=<id>`, or `--force` to regenerate).
- Scanned PDFs without a text layer produce no Markdown; those studies stay pending in final selection.

### Open-access PDFs by DOI in the final selection

- The **Final Selection** page has a **Find open-access PDFs** button (project owner only). It opens a window with the final selection's counts (studies, already with a PDF, without a DOI, to search) and starts a background job (`DownloadStudyPdf`) for each study that has a DOI and no PDF yet. The window shows a progress bar, found / not found / pending counts, the elapsed time, and each study's status with the source of its PDF or the reason none was found. The search keeps running if the window is closed, and the studies table refreshes as PDFs arrive.
- PDFs come from legal open-access sources:
  - [OpenAlex](https://openalex.org/) and [Europe PMC](https://europepmc.org/) (no account needed);
  - [Unpaywall](https://unpaywall.org/), with the **Contact e-mail** of the window (also sent to Crossref);
  - [Crossref](https://www.crossref.org/) publisher full-text links (version of record or accepted manuscript);
  - [CORE](https://core.ac.uk/) repository copies, with the **CORE API key** of the window ([free key](https://core.ac.uk/services/api#form));
  - the `citation_pdf_url` of the DOI landing page.

  The e-mail and the key are saved to the user's account (`users.unpaywall_email`, and `users.core_api_key` encrypted); the jobs read them from the project owner's account, so the key never goes into the queue payload. `UNPAYWALL_EMAIL` and `CORE_API_KEY` are server-wide fallbacks.
- **No grey literature:** only articles, reviews, book chapters, books, and letters are accepted, and only their published or accepted (peer-reviewed) versions. Preprints, theses, reports, and submitted versions are skipped; a DOI that Crossref types as grey literature (`posted-content`, `dissertation`, `report`...) is never downloaded. Crossref links, CORE, and the DOI page do not always label the version, so they are only tried after OpenAlex or Unpaywall confirms an open-access peer-reviewed version; CORE copies typed as thesis, preprint, or report are skipped.
- A downloaded PDF is converted to Markdown like an uploaded one. Many publishers block automated downloads with bot challenges (Cloudflare, IEEE Xplore), even for open-access articles. For those studies the window lists the open-access links that were found (CORE API links become their public `core.ac.uk/download` URL): open one in the browser, save the PDF, and send it with the study's **Upload PDF** button or **Bulk Upload PDFs**.

### Reading the article in the final selection

- The study window of a **final selection** (Human and AI) has **PDF** and **Article (Markdown)** tabs. The article is rendered from the stored Markdown when the tab is opened, and follows Previous/Next navigation.

### Extraction evidence: where each answer came from

- AI extractions ask the model, through `[[jsondata]]`, for an `_evidence` key next to the answers: for each field, up to 5 passages of the study copied word for word. They are stored in `extraction_study.evidence`; placeholders such as "not reported" and passages that only repeat the answer are dropped.
- The extraction study window has **PDF** and **Article (Markdown)** tabs. In the article, each field's passages are highlighted in its own color, with a legend that jumps to a field's passages and a switch to hide them.
- In the side panel, each field shows its color, how many of its passages were found in the article, the list of passages, and a **Show in article** button.
- Matching ignores line breaks, hyphenation, accents, punctuation and Markdown symbols; a passage cut by a table or page break, or slightly reworded, is matched by its beginning and end. Passages not found are listed but not highlighted.
- The JSON keys are the field IDs (`ED1`, `ED2`, ...), with the field name in each field's instruction; replies keyed by field name are still read.
- Only extractions processed after this change have evidence; earlier ones need to be processed again (which overwrites their values).
- In a test with GPT-4.1 mini on 3 studies (23 fields each), 136 of 138 passages were located in the article.

### Screening and extraction

- **Comments on decisions:** initial and final include/exclude decisions accept a free-text comment.
- **Extraction temperature:** AI extractions store their own temperature (0–2, default 0.1), sent to the model.
- **Extraction progress:** the extractions list shows whether each extraction is processing, completed, or stopped with studies left, and refreshes automatically. **Reprocess pending only** queues just the studies without an AI result.
- **Extraction page:** progress chart, a per-study extracted icon, and an *extracted / not extracted* filter.

## Architecture

| Layer | Technology |
|-------|------------|
| Web | Nginx |
| Application | Laravel (PHP-FPM) |
| Relational data | MySQL (projects, studies, protocol, screening decisions, extraction results, execution metadata) |
| Object storage | S3-compatible storage for study PDFs (production); local disk in development |
| Background jobs | Laravel queue (`database` driver); **two workers** in production (`queue-worker-1`, `queue-worker-2`) |
| AI routing | OpenRouter API or user-configured Ollama instance |

Long-running AI tasks (initial/final selection, extraction) run **asynchronously** so the UI stays responsive.

## Requirements

**Development (Docker):**

- Docker and Docker Compose
- Git

**Development (without Docker):**

- PHP 8.4+, Composer 2
- Node.js 20+ and npm
- MySQL 8.0+
- PHP extensions: `pdo_mysql`, `mbstring`, `zip`, `gd`, `opcache`
- Python 3.10+ with `pymupdf4llm` for the PDF to Markdown conversion (e.g. `python3 -m venv /opt/pdf-tools && /opt/pdf-tools/bin/pip install pymupdf4llm==1.28.2`, or set `PDF_MARKDOWN_PYTHON`)

**Production (Coolify):**

- Server with Docker
- MySQL (managed or external)
- AWS S3 bucket (or compatible) for PDFs
- `docker-compose.coolify.yml` as the Compose resource file

## Installation with Docker (recommended)

1. Clone the repository:

```bash
git clone https://github.com/robsonsilvalopes/AI_LitReview.git
cd AI_LitReview
```

2. Create the environment file (see [Configuration](#configuration)):

```bash
cp .env.example .env
```

3. Start containers:

```bash
docker compose up -d --build
```

4. Install dependencies and prepare the application (first time):

```bash
docker compose exec app composer install
docker compose exec app npm ci --legacy-peer-deps
docker compose exec app npm run build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

5. Open the application at [http://localhost:8080](http://localhost:8080) (nginx, port **8080**).

6. Register at `/register` or sign in at `/login`.

Local stack: `app` (PHP-FPM), `nginx`, `db` (MySQL), `queue` (one worker), and optional `ollama` (`http://ollama:11434` on the Docker network).

## Manual installation

```bash
composer install
npm ci --legacy-peer-deps
npm run build
cp .env.example .env   # if not done yet
php artisan key:generate
php artisan migrate
```

Run in separate terminals:

```bash
php artisan serve
php artisan queue:work --tries=3 --timeout=600
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

On Windows with WSL, keep the project on the Linux filesystem (`~/AILitReview`), not `/mnt/c/...`, for acceptable Docker and npm performance.

## Configuration

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Public application URL |
| `DB_*` or `DB_URL` | MySQL (`DB_URL` on Coolify) |
| `QUEUE_CONNECTION` | Use `database` for background selection/extraction jobs |
| `FILESYSTEM_DISK` | `local` (dev) or `s3` (production) |
| `AWS_*` | S3 credentials and bucket when using `s3` |
| `OPENROUTER_API_KEY` | Optional globally; per-user keys in **Settings** |
| `PULSE_ADMIN_EMAILS` | Comma-separated e-mails allowed to open the Pulse dashboard (`/pulse`); empty means nobody |
| `UNPAYWALL_EMAIL` | Optional server-wide contact e-mail for the Unpaywall API, used when the user leaves the **Contact e-mail** field of the PDF search window empty |
| `CORE_API_KEY` | Optional server-wide CORE API key, used when the user has not saved one in the PDF search window |
| `PDF_DOWNLOAD_TIMEOUT` | Seconds allowed for downloading one open-access PDF (default `60`) |
| `PDF_MARKDOWN_PYTHON` | Python with `pymupdf4llm` (default `/opt/pdf-tools/bin/python`, installed by the Docker image) |
| `PDF_MARKDOWN_TIMEOUT` | Seconds allowed for converting one PDF (default `300`) |

**Per-user AI settings** (after login):

1. **Settings** (`/settings`): OpenRouter API key and Ollama URL; and, under **Literature databases & open-access PDFs**, the contact e-mail (Unpaywall, Crossref) and the IEEE Xplore, Scopus (key and institutional token), NCBI/PubMed, and CORE API keys. Keys are saved encrypted and never shown again; an empty field keeps the saved key. The same values can also be entered in the Search databases and Find open-access PDFs windows.
2. **AI Models** (`/ai-models`): register models for screenings and extractions.

Ollama URL examples: `http://ollama:11434/` (Docker), `http://host.docker.internal:11434/` or `http://localhost:11434/` (host).

## Using the platform

### 1. Account and settings

- Sign in and configure OpenRouter and/or Ollama under **Settings**.

### 2. Create project and define the protocol

On the **dashboard** (`/`), create an SLR **project** and define:

- **Research questions**
- **Search strings** and the document databases they are run on (also used to tag BibTeX/RIS imports)
- **Inclusion criteria** and **exclusion criteria**
- **Extraction fields** (data extraction form)
- **Collaborators** (email invitation)
- **Prompt templates** for selection and extraction (the **Prompt Templates** card, also in the Review steps menu)

### 3. Import studies and remove duplicates

- **Studies:** import via Excel/CSV or **BibTeX/RIS** (choosing the source database), add records manually, or **bulk-upload PDFs**. Each PDF is converted to Markdown in the background.
- **Duplicates:** mark or resolve duplicate records before selection.

### 4. Initial selection (title and abstract)

- Open **Initial selection**.
- Create one or more **screenings** (Human, **AI OpenRouter**, or **AI Local**), each with a model, template, and responsible researcher.
- Run processing (requires active **queue workers**).
- Review LLM outputs (e.g. criterion-level scores and justifications) and record include / exclude / NA decisions.
- Export results to **XLS** when needed.

### 5. Final selection (full text)

- Open **Final selection** for studies that passed initial selection.
- Configure screenings that use `[[fullstudy]]` (the study's Markdown full text) in the template.
- In the study window, switch between the **PDF** and the rendered **Article (Markdown)**.
- Compare model outputs, then set final include/exclude decisions.

### 6. Data extraction

- Define or use extraction fields (e.g. `ED1`, `ED2`, …).
- Run human and/or AI extractions; review and accept or override field values.
- In the study window, open **Article (Markdown)** to see the passages behind each AI answer highlighted in the field's color, or use **Show in article** next to a field.
- Multiple extractions with different models can be compared.

### 7. Report generation

- Open **Report** for the protocol summary, **Study Flow Diagram (Sankey)**, and **RAG-based answers** to RQs from included studies.
- Open **Report → PRISMA 2020** for the PRISMA 2020 flow diagram (SVG/PNG) and the filled checklist (XLSX).
- Generate, print, or export evidence as provided in the UI.

### Tips

- Without queue workers, AI screenings and extractions remain in the `jobs` table.
- API screenings record tokens, latency, and cost per study for cost-aware model choice.
- Collaborators only access projects they were invited to.

## Prompt templates

Templates belong to a **project** (`templates.project_id`) and are managed in the **Prompt Templates** card of the project page (tabs for initial selection, final selection and data extraction), also reached from the **Review steps** menu (*Prompt templates*, done when the project has at least one template of each phase) and from the **Manage templates** link of the screening and extraction forms. The owner creates, edits and deletes them; collaborators view them. When creating an initial or final screening or an extraction, only the project's templates of that phase are listed, and a template of another project is refused. **Copy from another project** copies one of the owner's templates from another project (or one not linked to any project) into this one; the copy is independent.

When this was introduced, existing templates were linked to the project whose screenings or extractions used them (a template used by several projects was copied so each has its own); unused templates went to the owner's project when the owner had a single one, otherwise they stayed unlinked and can be copied into a project. The global **Templates** item of the left menu was removed: `/templates` leads to the dashboard, and `/projects/{id}/templates` to the card.

Templates support **dynamic variables** substituted at runtime:

| Variable | Used in |
|----------|---------|
| `[[title]]` | Initial selection (and other activities as needed) |
| `[[abstract]]` | Initial selection (title/abstract only, per SLR guidelines) |
| `[[fullstudy]]` | Final selection and extraction (full text, as Markdown generated from the PDF) |
| `[[inclusioncriteria]]` | Selection activities (from protocol ICs) |
| `[[exclusioncriteria]]` | Selection activities (from protocol ECs) |
| `[[pico]]` | Any template: the project's research question framework (PICO, PICOC, PCC...) with descriptions and keywords; empty when none |
| `[[jsondata]]` | Data extraction: the JSON to answer (one key per field ID plus `_evidence`) and the instructions for each field and for the passages |

The template form has an **Insert example** button that fills the template with an example structure for its type (`app/Services/TemplateExamples.php`), to adapt by replacing the text in `[BRACKETS]`:

- **Initial** and **final selection**: the standard **1-7 Likert scale** (1 = strongly disagree … 4 = neutral / cannot be determined … 7 = strongly agree) for each inclusion and exclusion criterion, screening rules, the overall decision rule, and the answer `{"criteria": [{"id": "IC1", "score": 6, "justification": "..."}], "overall": "include"}`. A criterion counts as applying with score 6 or 7, as read by `AiScreeningCriteria`.
- **Data extraction**: `[[jsondata]]`, `[[fullstudy]]`, extraction rules, and the answer with one key per field ID plus `_evidence`.

LLM responses for selection and extraction use a **JSON output schema** for deterministic parsing. Researchers can run **multiple screenings** on the same activity with different models and templates and compare results before human decisions.

Extraction responses have this shape (built by `[[jsondata]]` from the project's extraction fields):

```json
{
  "ED1": "2022",
  "ED2": "Journal article",
  "_evidence": {
    "ED1": ["Received 12 January 2022; accepted 3 March 2022"],
    "ED2": []
  }
}
```

If an extraction template shows its own example of the answer, use the field IDs as keys and include `_evidence`, so the example does not contradict the instructions. The template form warns when an extraction template lacks `[[jsondata]]`.

## Queues and workers

Background work uses `QUEUE_CONNECTION=database`.

**Local Docker:**

```bash
docker compose logs -f queue
```

The local worker runs `queue:work` as `www-data` (the PHP-FPM user), so files it writes, such as study Markdown, stay writable by the site.

**Production (Coolify):** `queue-worker-1` and `queue-worker-2` (two `php artisan queue:work` processes).

Workers keep the code in memory: after changing a job or a service it uses, restart them so new jobs run the new code:

```bash
docker compose exec app php artisan queue:restart
```

## Production deployment (Coolify)

Use **`docker-compose.coolify.yml`**.

| Service | Role |
|---------|------|
| `migrate` | One-shot: `scripts/coolify-migrate.sh` |
| `app` | PHP-FPM (Laravel) |
| `web` | Nginx — assign HTTPS domain here (port 80) |
| `queue-worker-1` / `queue-worker-2` | Queue workers |

Required variables include `APP_KEY`, `DB_URL`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, and `APP_URL` / `SERVICE_URL_WEB`.

MySQL and S3 are provisioned outside the compose file. For Ollama on the host, set `http://host.docker.internal:11434/` in user **Settings**.

Recommended health check on `web`: path **`/up`**, HTTP **200**.

## Useful commands

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose exec app php artisan migrate
docker compose exec app php artisan studies:generate-markdown
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app npm run dev
docker compose down
```

## Citation

If you use AI LitReview in research, please cite the SBES 2026 tool paper:

> Anderson Deizepe, Katia Romero Felizardo, and Roberto Pereira. *AI LitReview: A Web-Based Tool for AI-Assisted Systematic Literature Reviews.* 40th Brazilian Symposium on Software Engineering (SBES 2026).

## License

Released under the [MIT](LICENSE) license.
