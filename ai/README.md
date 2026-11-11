# Independent Healthcare AI Module

This module is additive and isolated under `ai/`. Existing PHP files, JavaScript, CSS, SQL schema, authentication, and appointment workflows are not modified.

## Windows setup

1. Install Python 3.11+.
2. Create a virtual environment:
   `py -m venv ai\.venv`
3. Install dependencies:
   `ai\.venv\Scripts\python -m pip install -r ai\requirements.txt`
4. Copy `ai\.env.example` to `ai\.env` and set a private `AI_API_KEY`.
5. Create a MySQL user with read-only permissions. Run as an administrator:
   `CREATE USER 'healthcare_ai_readonly'@'localhost' IDENTIFIED BY 'strong-password';`
   `GRANT SELECT ON healthcare_appointment.* TO 'healthcare_ai_readonly'@'localhost';`
6. Start the API from the project root:
   `ai\.venv\Scripts\python -m uvicorn backend.main:app --app-dir ai --host 127.0.0.1 --port 8000`
7. Serve `ai/frontend` with:
   `ai\.venv\Scripts\python -m http.server 8080 --directory ai/frontend`
8. Open `http://127.0.0.1:8080`.

The first local embedding request downloads the configured embedding model. To ingest approved documents, run:
`ai\.venv\Scripts\python -c "from config.settings import get_settings; from rag.pipeline import RAGPipeline; print(RAGPipeline(get_settings()).ingest())"`

Set `LLM_PROVIDER=openai`, `LLM_API_KEY`, and `LLM_MODEL` only when an external provider is approved. The default mock provider is safe for local API testing.

The recommendation endpoints query existing doctor and availability tables using parameterized SELECT statements. The appointment preview endpoint never writes to MySQL; the existing PHP workflow remains authoritative.

## Tests

From the project root:
`ai\.venv\Scripts\python -m pytest ai/tests -q`
