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
| **Data extraction** | Configurable extraction fields; human and AI extraction with structured JSON outputs. |
| **Prompt templates** | Reusable, per-activity templates with dynamic variables (see [Prompt templates](#prompt-templates)). |
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

### Full text as Markdown (pymupdf4llm)

- When a study gets or replaces a PDF (add, edit, bulk upload, or assign), a background job (`GenerateStudyMarkdown`) converts it to Markdown with [pymupdf4llm](https://pypi.org/project/pymupdf4llm/) and stores the `.md` next to the PDF (`studies.md_path`). Replacing the PDF or deleting the study removes the old Markdown.
- **Final selection** and **data extraction** (OpenRouter and Ollama) send this Markdown as `[[fullstudy]]`, replacing `pdftotext`. A study without Markdown yet is converted the first time the AI needs it.
- For PDFs uploaded before this feature, queue the conversion with `php artisan studies:generate-markdown` (optionally `--project=<id>`, or `--force` to regenerate).
- Scanned PDFs without a text layer produce no Markdown; those studies stay pending in final selection.

### Reading the article in the final selection

- The study window of a **final selection** (Human and AI) has **PDF** and **Article (Markdown)** tabs. The article is rendered from the stored Markdown when the tab is opened, and follows Previous/Next navigation.

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
git clone https://github.com/Deizepe/AI_LitReview.git
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
| `PDF_MARKDOWN_PYTHON` | Python with `pymupdf4llm` (default `/opt/pdf-tools/bin/python`, installed by the Docker image) |
| `PDF_MARKDOWN_TIMEOUT` | Seconds allowed for converting one PDF (default `300`) |

**Per-user AI settings** (after login):

1. **Settings** (`/settings`): OpenRouter API key and Ollama URL.
2. **AI Models** (`/ai-models`): register models for screenings and extractions.

Ollama URL examples: `http://ollama:11434/` (Docker), `http://host.docker.internal:11434/` or `http://localhost:11434/` (host).

## Using the platform

### 1. Account, settings, and templates

- Sign in and configure OpenRouter and/or Ollama under **Settings**.
- Create **prompt templates** at `/templates` for selection and extraction activities.

### 2. Create project and define the protocol

On the **dashboard** (`/`), create an SLR **project** and define:

- **Research questions**
- **Search strings** and the document databases they are run on (also used to tag BibTeX/RIS imports)
- **Inclusion criteria** and **exclusion criteria**
- **Extraction fields** (data extraction form)
- **Collaborators** (email invitation)

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
- Multiple extractions with different models can be compared.

### 7. Report generation

- Open **Report** for the protocol summary, **Study Flow Diagram (Sankey)**, and **RAG-based answers** to RQs from included studies.
- Generate, print, or export evidence as provided in the UI.

### Tips

- Without queue workers, AI screenings and extractions remain in the `jobs` table.
- API screenings record tokens, latency, and cost per study for cost-aware model choice.
- Collaborators only access projects they were invited to.

## Prompt templates

Templates are reusable across projects and support **dynamic variables** substituted at runtime:

| Variable | Used in |
|----------|---------|
| `[[title]]` | Initial selection (and other activities as needed) |
| `[[abstract]]` | Initial selection (title/abstract only, per SLR guidelines) |
| `[[fullstudy]]` | Final selection and extraction (full text, as Markdown generated from the PDF) |
| `[[inclusioncriteria]]` | Selection activities (from protocol ICs) |
| `[[exclusioncriteria]]` | Selection activities (from protocol ECs) |
| `[[jsondata]]` | Data extraction (schema of extraction fields) |

LLM responses for selection and extraction use a **JSON output schema** for deterministic parsing. Researchers can run **multiple screenings** on the same activity with different models and templates and compare results before human decisions.

## Queues and workers

Background work uses `QUEUE_CONNECTION=database`.

**Local Docker:**

```bash
docker compose logs -f queue
```

The local worker runs `queue:work` as `www-data` (the PHP-FPM user), so files it writes, such as study Markdown, stay writable by the site.

**Production (Coolify):** `queue-worker-1` and `queue-worker-2` (two `php artisan queue:work` processes).

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
