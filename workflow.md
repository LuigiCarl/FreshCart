# **FreshCart Order API – GitHub Development Workflow**

Gagawa tayo ng workflow na directly based sa assessment requirements mo. Ang goal ay mag-deliver ng working, reviewable API sa loob ng 4–6 hours. Gumamit ng ilang focused branches at Pull Requests kung kapaki-pakinabang; hindi kailangan ng hiwalay na branch para sa bawat maliit na requirement.

**Git strategy:** Main-only integration workflow with feature branches.

- `main` – Final integrated and reviewable application.
- `feature/*` – Individual features and requirements.
- `test/*` – Automated testing.
- `docs/*` – Documentation.
- `fix/*` – Bug fixes.

Walang `develop` branch. Lahat ng Pull Requests ay papunta sa `main`.

## **1. Overall development workflow**

\#chatgpt-mermaid-\_r_j0\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(237, 237, 237);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_j0\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_j0\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_j0\_ .error-icon{fill:rgb(48, 48, 48);}#chatgpt-mermaid-\_r_j0\_ .error-text{fill:rgb(237, 237, 237);stroke:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_j0\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_j0\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_j0\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_j0\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_j0\_ .marker{fill:rgb(175, 175, 175);stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_j0\_ .marker.cross{stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_j0\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_j0\_ p{margin:0;}#chatgpt-mermaid-\_r_j0\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .cluster-label text{fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .cluster-label span{color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .cluster-label span p{background-color:transparent;}#chatgpt-mermaid-\_r_j0\_ .label text,#chatgpt-mermaid-\_r_j0\_ span{fill:rgb(237, 237, 237);color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .node rect,#chatgpt-mermaid-\_r_j0\_ .node circle,#chatgpt-mermaid-\_r_j0\_ .node ellipse,#chatgpt-mermaid-\_r_j0\_ .node polygon,#chatgpt-mermaid-\_r_j0\_ .node path{fill:rgb(9, 23, 44);stroke:rgb(31, 78, 148);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .rough-node .label text,#chatgpt-mermaid-\_r_j0\_ .node .label text,#chatgpt-mermaid-\_r_j0\_ .image-shape .label,#chatgpt-mermaid-\_r_j0\_ .icon-shape .label{text-anchor:middle;}#chatgpt-mermaid-\_r_j0\_ .node .katex path{fill:#000;stroke:#000;stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .rough-node .label,#chatgpt-mermaid-\_r_j0\_ .node .label,#chatgpt-mermaid-\_r_j0\_ .image-shape .label,#chatgpt-mermaid-\_r_j0\_ .icon-shape .label{text-align:center;}#chatgpt-mermaid-\_r_j0\_ .node.clickable{cursor:pointer;}#chatgpt-mermaid-\_r_j0\_ .root .anchor path{fill:rgb(175, 175, 175)!important;stroke-width:0;stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_j0\_ .arrowheadPath{fill:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_j0\_ .edgePath .path{stroke:rgb(175, 175, 175);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .flowchart-link{stroke:rgb(175, 175, 175);fill:none;}#chatgpt-mermaid-\_r_j0\_ .edgeLabel{background-color:rgb(0, 0, 0);text-align:center;}#chatgpt-mermaid-\_r_j0\_ .edgeLabel p{background-color:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_j0\_ .edgeLabel rect{opacity:0.5;background-color:rgb(0, 0, 0);fill:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_j0\_ .labelBkg{background-color:rgba(0, 0, 0, 0.5);}#chatgpt-mermaid-\_r_j0\_ .cluster rect{fill:rgb(48, 48, 48);stroke:rgba(255, 255, 255, 0.15);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .cluster text{fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ .cluster span{color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ div.mermaidTooltip{position:absolute;text-align:center;max-width:200px;padding:2px;font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:12px;background:rgb(48, 48, 48);border:1px solid rgba(255, 255, 255, 0.15);border-radius:2px;pointer-events:none;z-index:100;}#chatgpt-mermaid-\_r_j0\_ .flowchartTitleText{text-anchor:middle;font-size:18px;fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_j0\_ rect.text{fill:none;stroke-width:0;}#chatgpt-mermaid-\_r_j0\_ .icon-shape,#chatgpt-mermaid-\_r_j0\_ .image-shape{background-color:rgb(0, 0, 0);text-align:center;}#chatgpt-mermaid-\_r_j0\_ .icon-shape p,#chatgpt-mermaid-\_r_j0\_ .image-shape p{background-color:rgb(0, 0, 0);padding:2px;}#chatgpt-mermaid-\_r_j0\_ .icon-shape .label rect,#chatgpt-mermaid-\_r_j0\_ .image-shape .label rect{opacity:0.5;background-color:rgb(0, 0, 0);fill:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_j0\_ .label-icon{display:inline-block;height:1em;overflow:visible;vertical-align:-0.125em;}#chatgpt-mermaid-\_r_j0\_ .node .label-icon path{fill:currentColor;stroke:revert;stroke-width:revert;}#chatgpt-mermaid-\_r_j0\_ .node .neo-node{stroke:rgb(31, 78, 148);}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_j0\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_j0\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node .neo-line path{stroke:rgb(31, 78, 148);filter:none;}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_j0\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_j0\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_j0\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_j0\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_j0\_ .node text{font-size:14px;font-weight:600;letter-spacing:normal;fill:rgb(153, 206, 255);}#chatgpt-mermaid-\_r_j0\_ .edgeLabels text{font-size:13px;font-weight:600;letter-spacing:-0.08px;fill:rgb(153, 206, 255);}#chatgpt-mermaid-\_r_j0\_ .node tspan[font-weight="normal"],#chatgpt-mermaid-\_r_j0\_ .edgeLabels tspan[font-weight="normal"]{font-weight:600;}#chatgpt-mermaid-\_r_j0\_ .edgeLabel .label rect{opacity:1;rx:13px;ry:13px;fill:rgb(0, 14, 26);stroke:rgb(26, 62, 95);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .node rect,#chatgpt-mermaid-\_r_j0\_ .node circle,#chatgpt-mermaid-\_r_j0\_ .node ellipse,#chatgpt-mermaid-\_r_j0\_ .node polygon,#chatgpt-mermaid-\_r_j0\_ .node path{fill:rgb(0, 40, 77);stroke:rgba(255, 255, 255, 0.1);stroke-width:1px;}#chatgpt-mermaid-\_r_j0\_ .node rect{rx:16px;ry:16px;}#chatgpt-mermaid-\_r_j0\_ .mermaid-decision .label-container{fill:rgb(0, 14, 26);stroke:rgb(26, 62, 95);stroke-dasharray:2,2;}#chatgpt-mermaid-\_r_j0\_ .edgePaths .flowchart-link{stroke:rgb(175, 175, 175);stroke-width:1px;stroke-linecap:round;stroke-linejoin:round;}#chatgpt-mermaid-\_r_j0\_ .marker{fill:rgb(175, 175, 175);stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_j0\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}mainCreate feature branchImplement featureRun relevant testsTests passing?Commit and PushCreate Pull Request to mainReview code and changesApproved?Merge into mainUpdate local mainNext feature branchNoYesNoYes

## **2. Milestone roadmap based on assessment requirements**

Sundin ang dependencies, pero panatilihing maliit ang bilang ng branches at PRs. Puwedeng pagsamahin ang milestones kung mas angkop sa oras at laki ng pagbabago. Magdagdag ng focused tests kasabay ng bawat feature; huwag ipagpaliban ang lahat ng testing hanggang sa huli.

| Order | Suggested branch | Deliverable and acceptance criteria |
| ----- | ---------------- | ----------------------------------- |
| 01 | `feature/order-foundation` | I-configure ang MySQL at database queue kung kailangan; idagdag ang `.env.example`, products/orders/order_items schema, indexes at foreign keys, models/relationships/status values, factory at seeder na may hindi bababa sa 10 products. |
| 02 | `feature/order-checkout-api` | I-set up ang `/api/v1` routes, JSON API Resources, product listing (15 per page), Form Request validation, at order placement sa service/action class. Sa transaction, i-lock ang products, i-validate ang stock, kunin ang presyo sa database, kalkulahin ang totals, gumawa ng pending order at items, at i-dispatch ang job pagkatapos mag-commit. Ibalik ang `201` at malinaw na `422` error na may pangalan ng product kapag kulang ang stock. |
| 03 | `feature/order-processing` | Iproseso ang order sa database queue: gawin at i-save ang invoice sa `storage/app/invoices`, i-update ang status at `processed_at`, at mag-queue ng confirmation email gamit ang log mail driver. Magtakda ng 3 tries at backoff; sa `failed()`, i-save ang failed status at dahilan. Huwag ulitin ang trabaho sa completed order. |
| 04 | `feature/order-management-api` | Idagdag ang pag-lista ng orders na may pagination at status/email filters, pagtingin ng order at item/product names, at cancellation ng pending order lamang. Gawing atomic ang pag-check ng status at pagbabalik ng stock upang maiwasan ang race sa background job. |
| 05 | `test-and-docs/order-api` | Kumpletuhin ang hindi bababa sa 5 feature tests, consistent JSON error responses, README setup/assumptions/queue instructions, AI disclosure, at Postman collection o cURL examples. Patakbuhin ang buong assessment checklist bago ang final PR. |

### Critical acceptance checks

- Mabilis ang checkout dahil hindi gumagawa ng invoice o nagpapadala ng email sa loob ng request. Suriin ang response time sa lokal na setup; huwag gawing flaky ang CI sa pamamagitan ng mahigpit na timing assertion.
- Walang stock na bababa sa zero. Subukan ang transactional rollback at, gamit ang MySQL, mag-verify ng concurrent checkout dahil maaaring hindi maipakita ng SQLite ang parehong row-lock behavior.
- May test para sa validation, matagumpay na checkout, insufficient stock/rollback, listing filters at details, pending-only cancellation/stock restoration, at queued processing/retry/failure behavior.
- Tinutukoy ng `ProcessOrder::failed()` ang failure ng order-processing job. Dahil hiwalay na queued job ang email, tiyaking nakikita rin ang mail-job failures sa Laravel failed-job/logging mechanism at idokumento kung paano ito naaapektuhan sa order status.
- Ang 404, 422, at 500 responses ay JSON at sumusunod sa iisang error format.

## **3. Git commands for each focused PR**

Use this same workflow every time.

**Step 1 — Start from updated main**

```
git switch main
git pull origin main
```

**Step 2 — Create a feature branch**

```
git switch -c feature/order-foundation
```

**Step 3 — Develop and test**

```
php artisan migrate:fresh
php artisan test
```

Only run `migrate:fresh` against your local development database because it deletes existing tables and data.

**Step 4 — Commit and push**

```
git status --short
git add <files-you-changed>
git commit -m "feat: add order foundation"
git push -u origin feature/order-foundation
```

**Step 5 — Create Pull Request**

**Pull Request**

Ready for review

base

`main`

compare

`feature/order-foundation`

**PR title**\
`feat: add order foundation`

**PR description**

- Summarize the schema, models, and seed data included in this PR.
- Explain any assumptions or schema choices.

**Testing**

- Relevant migrations and tests pass.
- No stock or relationship regressions.
- No unrelated changes included.

Stage only files related to the PR; avoid `git add .` if unrelated or generated files may be present. After reviewing and merging, update your local main before creating another branch.

```
git switch main
git pull origin main
```

## **4. Pull Request and commit naming convention**

Use these consistently:

| Type        | Purpose                       |
| ----------- | ----------------------------- |
| `feat:`     | New functionality             |
| `fix:`      | Bug fixes                     |
| `test:`     | Tests                         |
| `docs:`     | Documentation                 |
| `chore:`    | Configuration and maintenance |
| `refactor:` | Code restructuring            |

Example commit history:

```
feat: create product and order migrations
feat: implement eloquent relationships
feat: add product listing endpoint
feat: implement transactional order placement
fix: prevent negative stock during checkout
feat: add asynchronous order processing
feat: implement queued email notification
test: cover order processing workflow
docs: add project setup instructions
```

## **5. Optional requirements – separate branches**

Huwag muna natin itong unahin. Complete muna ang core assessment.

| Priority   | Branch                          | Feature                                          |
| ---------- | ------------------------------- | ------------------------------------------------ |
| Optional 1 | `feature/idempotency`           | Prevent duplicate checkout using Idempotency-Key |
| Optional 2 | `feature/sanctum-auth`          | Authentication and customer order ownership      |
| Optional 3 | `feature/low-stock-alert`       | Admin low-stock email                            |
| Optional 4 | `feature/pending-order-cleanup` | Scheduled cancellation after 30 minutes          |
| Optional 5 | `feature/order-rate-limiting`   | 10 requests/minute                               |
| Optional 6 | `chore/docker-setup`            | Docker Compose with MySQL and queue worker       |

My suggestion: prioritize idempotency and rate limiting after all core requirements pass.

## **6. Definition of Done**

Bago gumawa ng Pull Request, dapat pasado ang following checklist:

**PR readiness checklist**

0/10 completed

**Development**

Code follows Laravel conventions

No hardcoded credentials or secrets

**Database**

Relevant migrations execute successfully

Relationships and foreign keys work

**Testing**

At least 5 focused feature tests cover the assessment's core behavior

Stock checks, transactional rollback, and cancellation are verified; use MySQL to verify row-lock behavior

Queued processing, duplicate-job safety, retries, and failures are verified

Checkout does not generate invoices or send email synchronously

404, 422, and 500 responses use the consistent JSON error format

**GitHub**

No unrelated changes in the branch

Meaningful commit messages

Pull Request description completed

Reset checklist

## **7. DevOps responsibilities**

Since you're handling the full development and DevOps side, include these in the final delivery:

- `.env.example` – Environment configuration template.
- Database queue configuration – MySQL-backed jobs.
- Queue worker instructions – `php artisan queue:work`.
- Failed job monitoring – `php artisan queue:failed`.
- Automated tests – `php artisan test`.
- GitHub Actions – Run tests when a PR is opened (if time permits).
- README – Installation, migration, seeding, queue worker, tests, assumptions, and AI disclosure.

**One important assessment detail:** Your README must disclose AI usage because the instructions explicitly allow AI tools but require disclosure.

## **8. Final GitHub presentation**

Your managers should see this when they open your repository:

1. `main` contains the complete working Laravel API.
2. A small number of focused branches show meaningful increments; avoid creating branches solely to mirror every requirement.
3. Pull Requests show what changed, why, and which tests were run.
4. Commit history demonstrates your implementation process.
5. README explains how to run and evaluate the project.
6. Tests demonstrate that the critical requirements work.

**Recommended order:** Kumpletuhin muna ang schema at checkout bago ang background processing; saka idagdag ang remaining order endpoints at tapusin ang acceptance checks at documentation. Huwag unahin ang optional features hangga't hindi pasado ang lahat ng core requirements. Panatilihing simple ang Git history at magbukas lang ng PRs kung makakatulong ito sa review o sa pagpapakita ng trabaho.