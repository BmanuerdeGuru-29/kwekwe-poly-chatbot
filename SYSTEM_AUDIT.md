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
- Added confidence-aware chat responses, office handoff suggestions, suggested next actions, and a search endpoint over the indexed knowledge base.
- Added JSONL-backed analytics and feedback capture for query monitoring and answer-quality review.
- Added admin endpoints and a browser-based admin console for local ingestion, upload-driven indexing, website sync, analytics, and feedback review.
- Added a lightweight official-website sync service for crawling approved Kwekwe domains into the vector store.
- Rebuilt the public frontend into a branded, mobile-friendly assistant with guided journeys, search, language selection, citations, and feedback controls.
- Removed unsafe mock personal-data behavior from student verification, balances, and results flows until real institutional integrations are connected.

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

## Benchmark Comparison

- Compared with Penn State's LionChat, this project is architecturally flexible but still narrower in operational coverage. LionChat spans admissions, bursar, registrar, and student-aid questions across multiple enrollment sites, while this repo still depends on a smaller bundled knowledge set and mock student-service tools.
- Compared with Western Washington University's Fisher, this project already has a stronger self-hosted engineering base, but Fisher is more mature in routing users to the right office when the bot is not the best channel.
- Compared with the University of Houston's Shasta, this project has the right direction for student-specific workflows, but not the production readiness. Shasta already supports personalized questions like balances, transcript receipt, and FAFSA status, while `backend/services/langchain_tools.py` is still placeholder logic.
- Compared with Northwood University's AI search and chatbot experience, this bot is behind on website-wide discovery. Northwood pairs chat with AI search, instant summaries, and user feedback loops across the site.
- Compared with Ocelot-style higher-ed platforms, this project is behind in multilingual delivery, live-chat escalation, analytics, privacy controls, and large-scale admin tooling, but it can outperform them on local customization and Zimbabwe-specific channel design if implemented well.
- Compared with Ivy.ai-style deployments, this project is behind in published accessibility, uptime, support, and operational governance.
- Compared with Mainstay-style student-success bots, this project is behind in proactive messaging. It answers questions, but it does not yet drive outcomes through reminders, nudges, and follow-up campaigns.

## Additional Improvements From Benchmarking

1. Build a real student-service layer behind the chatbot.
   Connect verified flows for fee balances, application status, transcript receipt, portal support, and exam-result availability to live institutional systems rather than mock data.

2. Add confidence-based answer handling.
   Introduce high-confidence, low-confidence, and no-confidence response modes so the bot can answer, show source links, or escalate cleanly instead of guessing.

3. Add human handoff and office routing.
   Let the bot transfer difficult cases to Admissions, ICT, Examinations, Student Affairs, or Accounts through email, WhatsApp escalation, or live chat windows.

4. Turn the chatbot into an AI search layer for the website.
   Index the public website, notices, brochures, calendars, portal manual, code of conduct, and division pages so users can search and chat against the same official content.

5. Add continuous content sync from Kwekwe's official website.
   The bot should automatically refresh from current notices such as fees, intake dates, academic calendar updates, vacancies, and HEXCO announcements.

6. Add multilingual support.
   Start with English, then add Shona and Ndebele for common student-service journeys, especially admissions, payments, accommodation, and portal help.

7. Add proactive WhatsApp and SMS campaigns.
   Use the bot for reminders about application deadlines, registration windows, fee deadlines, result-collection notices, orientation dates, and portal setup steps.

8. Add response feedback loops.
   Include thumbs up/down, issue reporting, and an admin queue for improving weak answers and uncovering missing knowledge.

9. Add a content-governance workflow.
   Assign content owners by office so Admissions owns intake content, Accounts owns fees, ICT owns portal instructions, and Examinations owns HEXCO/result notices.

10. Add stronger analytics.
    Track top intents, failed queries, abandonment, conversion to application, portal-support deflection, busiest hours, WhatsApp vs web usage, and office-routing trends.

11. Add accessibility targets.
    Aim for WCAG 2.1 AA on the widget, keyboard navigation, screen-reader labels, high-contrast mode, and readable mobile layouts.

12. Add privacy, consent, and security controls.
    Student-specific answers should require verification, sensitive data should not be exposed in anonymous chat, and every personal-data flow should be logged and policy-backed.

13. Add Kwekwe-specific journeys, not just generic FAQ.
    Create guided flows for `Apply Now`, `January/August intake`, `fees in USD/ZiG`, `banking and mobile money`, `student portal login`, `off-campus accommodation`, `ICT support`, and `HEXCO results collection`.

14. Add a staff-facing admin console.
    Staff should be able to upload new PDFs, approve answers, inspect failed questions, trigger re-indexing, and publish urgent notices without code changes.

15. Add low-bandwidth and mobile-first optimization.
    Keep WhatsApp as a first-class channel, reduce heavy frontend assets, support flaky connections, and optimize for the phone-first experience most students will likely use.

16. Add application-conversion features.
    Capture interest in programs, route users to the correct application page, and save high-intent leads for follow-up by Admissions.

17. Add student-life and welfare coverage.
    Expand the knowledge base to include accommodation, counseling, disability support, SRC information, campus life, transport, and health/inclusivity services.

18. Add a formal operating model.
    Define who reviews content, how often data is refreshed, what the fallback path is, what the uptime target is, and how incidents are handled.

## Recommended Delivery Sequence

1. Finish replacing mock service tools with real backend integrations.
2. Unify the frontend into one supported production surface.
3. Add staff-managed document refresh and approval workflows.
4. Add monitoring, analytics, and multilingual support.
