# IP Registration Notes

Prepared for the Kwekwe Polytechnic chatbot project on 2026-04-08.

This is a practical product note, not legal advice. Zimbabwe filing practice can change, so confirm the current route with the Companies and Intellectual Property Office of Zimbabwe (CIPZ) or a registered IP agent before filing.

## Main IP worth protecting

### 1. Trademark / service mark
- Best fit for: the chatbot name, logo, launcher icon, tagline, and any distinctive product branding.
- Likely candidates:
  - `Kwekwe Poly AI`
  - the chatbot logo / badge
  - any slogan such as "Official Assistant"
- Filing routes:
  - Zimbabwe national filing through CIPZ.
  - Regional filing through ARIPO under the Banjul Protocol if protection is needed in multiple member states.
- Official references:
  - WIPO Zimbabwe profile: https://www.wipo.int/en/web/country-profiles/ZW
  - ARIPO trademarks: https://www.aripo.org/public/ip-services/trademarks

### 2. Copyright
- Best fit for: source code, chatbot prompts, knowledge-base articles, UI copy, documentation, graphics, logos, and training or support materials.
- For this project, likely copyright assets include:
  - PHP backend code
  - JavaScript widget code
  - CSS and visual assets
  - markdown knowledge files
  - admin and public UI copy
  - the logo and branded graphics
- Practical note:
  - Copyright usually protects original works automatically once created.
  - Zimbabwe's 2006 Copyright and Neighbouring Rights Regulations also contemplate formal applications and register entries for some copyright-related matters. I am inferring from the text that a recordal strategy may be available, but you should confirm the exact current filing practice with CIPZ or local counsel before relying on it.
- Official references:
  - Zimbabwe Copyright and Neighbouring Rights Act on WIPO Lex: https://www.wipo.int/wipolex/en/legislation/details/8864
  - Zimbabwe Copyright and Neighbouring Rights Regulations, 2006 on WIPO Lex: https://www.wipo.int/wipolex/en/legislation/details/9037

### 3. Patent or utility model
- Best fit for: a genuinely new technical invention, not a normal "chatbot + API" implementation.
- Possible candidate only if the system includes a novel technical method, for example:
  - a new low-resource retrieval pipeline
  - a new verified handoff mechanism
  - a novel offline ranking and escalation workflow with measurable technical effect
- Caution:
  - novelty can be lost by public disclosure, demos, or publishing code before filing.
  - many software ideas are too ordinary to justify a filing.
- Filing routes:
  - Zimbabwe national filing through CIPZ.
  - Regional filing through ARIPO under the Harare Protocol.
- Official references:
  - WIPO Zimbabwe profile: https://www.wipo.int/en/web/country-profiles/ZW
  - ARIPO patents: https://www.aripo.org/public/ip-services/patents
  - ARIPO utility models: https://aripo.org/ip-services/utility-model

### 4. Industrial design
- Best fit for: the ornamental appearance of a product, device, or other visual article.
- Most relevant only if this chatbot becomes:
  - a branded kiosk
  - a dedicated hardware terminal
  - a packaged physical product with a distinctive appearance
- For a pure web chatbot, this is usually lower priority than trademark and copyright.
- Official references:
  - ARIPO industrial designs: https://aripo.org/ip-services/industrial-design
  - Zimbabwe Industrial Designs Act on WIPO Lex: https://www.wipo.int/wipolex/en/text/130495

## High-priority actions for this project

1. Confirm who owns the brand.
   - If "Kwekwe Polytechnic", the crest, or official school marks are used, ownership likely sits with the institution, not the developer personally.
   - Make sure there is written permission or assignment before filing anything in your own name.

2. Secure the trademark assets first.
   - Clear the name and logo.
   - File the brand and logo in the right classes.
   - Reserve matching domain names and social handles.

3. Keep copyright evidence organized.
   - Keep dated source files, design files, logos, and authorship records.
   - Keep written contractor or employee assignment terms if more than one person contributed.

4. Only explore patent or utility model protection after a novelty review.
   - If the system is mainly a school-specific assistant with standard retrieval and OpenAI fallback, trademark and copyright will usually matter more.

## Shortlist of likely protectable assets in this repo

- Brand name: `Kwekwe Poly AI`
- Product descriptor: `Official Assistant`
- Logo: `logo.png`
- Public website and widget look-and-feel
- Source code in `app/`, `api/`, `index.php`, `kwekwe-chat-widget.js`, and `kwekwe-chat-widget.css`
- Knowledge-base content in `data/knowledge/`
- Integration and product documentation in the markdown files

## Usually valuable, but not registered

- Trade secrets:
  - internal prompts
  - admin keys and secret settings
  - analytics heuristics
  - deployment workflows
  - unpublished product roadmap

- Contract rights:
  - ownership assignments
  - licensing terms
  - reseller or institutional deployment agreements
