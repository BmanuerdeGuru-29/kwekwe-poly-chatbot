# Kwekwe Polytechnic Chatbot System Audit

## Current System Shape

- `main.py` is the primary FastAPI application for the RAG-enabled backend.
- `backend/` contains the vector store, ingestion pipeline, chat API, WhatsApp webhook, and session services.
- `frontend/index.html` is the most deployment-ready web UI in the repository today.
- `frontend/src/` contains a second React widget implementation that is useful as a future reusable embed surface, but it is not yet the cleanest production path.
- `data/sample_docs/` is the strongest local knowledge source currently bundled with the project.

## What Was Blocking Production Readiness

- The frontend expected `/api/v1/chat/query`, but the backend routers were mounted without the `/api/v1` prefix.
- Redis initialization in `session_manager.py` was asynchronous but invoked during object construction without awaiting, so session storage could silently fall back or fail unpredictably.
- Startup ingestion recreated simplified sample documents instead of prioritizing the richer repository knowledge base.
- Vector ingestion was not idempotent, which made repeated startups risky for a persistent Chroma collection.
- The shipped frontend logo path was broken because `frontend/index.html` referenced `./logo.png` while the asset only existed at the repository root.
- The frontend Docker image was configured around a Node/Vite build path that did not match the actual deployed HTML surface.

## Improvements Applied

- Mounted API routers under `settings.API_V1_STR`, which makes the backend match the documented and frontend-facing contract.
- Initialized session storage explicitly during application startup and before session operations.
- Seeded the vector database from `data/sample_docs/` when empty, rather than rebuilding a smaller mock-only knowledge base.
- Added chunking and stable chunk ids during ingestion so retrieval quality is better and repeated ingestion updates existing chunks cleanly.
- Switched Chroma writes to `upsert` and guarded queries against empty collections.
- Updated the live frontend to call the corrected health and chat endpoints.
- Added the Kwekwe Poly logo to the frontend folder and updated the visible motto to `Beyond The Information Given`.
- Simplified the frontend Docker image to serve the existing static frontend directly.

## Highest-Value Next Enhancements

1. Consolidate the two frontend implementations into one production UI so the static site, React widget, and embed workflow do not drift apart.
2. Replace the mock tool outputs in `backend/services/langchain_tools.py` with real integrations to the admissions, fees, results, and student systems.
3. Add document provenance to responses in a more user-friendly way, including source title, last-updated date, and confidence thresholds for fallback answers.
4. Introduce admin ingestion commands so staff can refresh prospectuses, fee notices, timetables, and HEXCO circulars without redeploying.
5. Add authentication and verification flows before exposing personal student data such as balances or exam results.
6. Move CORS, allowed origins, and rate-limit settings into environment-specific configuration instead of permissive defaults.
7. Add automated tests for API routing, ingestion idempotency, and a few representative RAG queries grounded in Kwekwe documents.
8. Add observability: request ids, structured logs, ingestion metrics, and uptime/error dashboards.
9. Localize key flows for English, Shona, and Ndebele once the English knowledge base is stable.

## Branding Recommendations

- Use the existing Kwekwe Poly logo consistently in the launcher, chat header, welcome panel, and landing hero.
- Keep the brand palette anchored in green and gold, with blue used sparingly for secondary emphasis.
- Use the institutional line `Beyond The Information Given` as the primary supporting text under the college name.
- Add an institutional footer note that answers are sourced from official Kwekwe Polytechnic documents and may direct users to admissions or ICT for authoritative follow-up.

## Recommended Delivery Sequence

1. Finish replacing mock service tools with real backend integrations.
2. Unify the frontend into one supported production surface.
3. Add staff-managed document refresh and approval workflows.
4. Add monitoring, analytics, and multilingual support.
