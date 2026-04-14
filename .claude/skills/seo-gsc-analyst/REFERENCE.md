# SEO GSC Analyst — Reference

Deep-dive reference for the `seo-gsc-analyst` skill. Load this when the user needs nuanced threshold explanations, chart interpretation, or advanced analysis methodology.

---

## Filter Threshold Rationale

### Why 40% for `criticalLoss`?
Industry-standard "algorithmically significant" drop threshold. Impressions fluctuate ±15% naturally (seasonality, crawl variance). Anything >40% over a non-holiday period is statistically unlikely to be noise — it signals a structural problem (penalty, content quality issue, deindex).

**Tuning guidance**: For news/trending sites, raise to 55% (high natural volatility). For stable evergreen content, use 25%.

### Why position >2 for `decreasing`?
GSC position is an *average* — a keyword sitting at 3.8 that moves to 6.2 is effectively a page-1 → mid-page-1 shift, meaningful. A 2-point buffer filters SERP shuffling noise (result order shifts by ≤1 position are common with featured snippets / PAA boxes).

### Why 100 impressions for `lowCTRHighImpressions`?
Below 100 impressions the CTR sample is too small to be actionable. A keyword at 2/50 impressions (4% CTR) could be 0/1 or 2/2 on a different day — too noisy. At 100+ impressions the signal is statistically reliable.

### Why 0.5% CTR as the `lowCTRHighImpressions` ceiling?
Position-adjusted CTR benchmarks (Backlinko, Advanced Web Ranking studies):
- Position 1: ~27–32% CTR
- Position 3: ~10–11% CTR
- Position 10: ~2.5–3% CTR
- Position 11+: <2%

A result with any meaningful position that gets <0.5% CTR is performing at less than 20% of the expected benchmark. This is the "SERP impression but snippet is broken" territory.

### `lostTop3` vs `lostTop10`
- TOP3 captures ~75% of potential clicks (position 1 alone ~30%)
- Losing TOP3 is immediately revenue-impacting; losing TOP10 is opportunity loss
- Treat `lostTop3` as urgent, `lostTop10` as high-priority within the next sprint

---

## CTR Benchmark Table by Position

| Position | Expected CTR | Red flag threshold |
|----------|-------------|-------------------|
| 1 | 27–32% | < 15% |
| 2 | 15–19% | < 8% |
| 3 | 10–11% | < 5% |
| 4–5 | 6–8% | < 3% |
| 6–10 | 2–4% | < 1% |
| 11–20 | 0.5–2% | < 0.3% |
| 21+ | < 0.5% | Deprioritise |

**Caveat**: Branded keywords always have higher CTR. Non-branded informational queries are lower. Navigational queries are highest. Adjust expectations per intent.

---

## Date Range Window Strategy

GSC comparison computes `current` vs `previous` over equal-length windows:

| Window | Best for |
|--------|----------|
| 7d | Rapid issue detection, post-publish monitoring |
| 28d | Standard performance review, algorithm impact assessment |
| 90d | Trend identification, seasonal baseline |
| 180d | Content decay, long-term competitor gains |
| 16 months | Year-over-year, content lifecycle analysis |

**Rule of thumb**: Use 28-day windows for weekly reporting. Use 7-day windows when actively investigating a recent change. Use 90-day for quarterly strategic reviews.

**Seasonality pitfall**: Always compare same-calendar-period year-over-year for any site with seasonal traffic. A January vs. December comparison will always show a drop for most e-commerce sites.

**GSC hard limits**: Max 500 rows per API call, 25,000 rows total per query, max 16 months of history, max 486-day date range per request.

---

## Data Sorting Strategies

### By impact (default recommended order)
1. `clicks_change` ascending → immediate revenue loss (worst performers first)
2. `impressions_change` ascending → leading indicator of future click loss
3. `position` ascending + `ctr` ascending → highest-ranked but lowest engagement (snippet problem)
4. `clicks` descending → highest-value pages to protect
5. `impressions` descending + `ctr` ascending → largest optimisation opportunity pool

### Segmentation approaches
- **By device**: Mobile vs. Desktop position divergence >5 = separate optimisation needed
- **By country**: Top 3 markets first; if a market is declining while others are stable → local algorithm or competitor issue
- **By intent cluster**: Group keywords by head term; analyse cluster health collectively
- **By page**: Use `/page-keywords` — if a single page's keyword footprint is collapsing, the page itself has the problem (not individual keywords)

---

## Cannibalization Scoring Methodology

Cannibalization is detected when multiple pages rank for the same keyword. Severity scoring:

| Score | Condition | Recommended Action |
|-------|-----------|-------------------|
| 🔴 Critical | 3+ pages ranking, top URL position > 5 | Consolidate pages + 301 redirect |
| 🟠 High | 2 pages both in TOP10, positions within 5 of each other | Canonical to weaker page |
| 🟡 Medium | 2 pages, one in TOP10, one outside | Internal linking to dominant page |
| 🟢 Low | 2 pages, only one in TOP20 | Monitor, no immediate action |

**How to find**: `/keyword-pages?keyword=<term>` — if `rows` contains >1 URL with `current.impressions > 0`, cannibalization is confirmed.

**Investigation steps**:
1. Filter cannibalization results by highest impression volume first
2. Identify the "intended" page (usually older, more backlinks)
3. Check if the competing page is a pagination, tag, or category overlap
4. For blog posts vs. service pages competing: consolidate or add rel=canonical

**Solutions comparison**:

| Solution | Pros | Cons | Risk | Reversible? | Best when |
|----------|------|------|------|-------------|-----------|
| 301 redirect weaker → stronger | Consolidates link equity; simple | Permanent; loses unique content | Low | No | One page is clearly dominant |
| Canonical tag | Non-destructive; preserves both URLs | Doesn't pass 100% equity; slower result | Low | Yes | Both pages have unique value |
| Merge content + redirect | Best equity consolidation | Time-intensive | Med | No | High content overlap |
| Internal link restructure | Low risk; keeps both pages | Slow effect; may not fully resolve | Low | Yes | Pages serve different intents |

---

## Chart Pattern Recognition

### V-Shape Recovery
- **Shape**: Sharp drop then symmetric recovery
- **Diagnosis**: Algorithm update rolled back; temporary technical issue fixed
- **Action**: Confirm with Coverage report. If recovery incomplete → content quality gap remains

### Slow Bleed
- **Shape**: Gradual monotonic decline over 60–180 days
- **Diagnosis**: Content decay; keyword intent drift (SERP now shows different content type)
- **Action**: Audit top-declining keywords, compare content freshness vs. ranking competitors

### Spike-and-Drop
- **Shape**: Large traffic spike (1–7 days) then return to baseline
- **Diagnosis**: Viral mention, social amplification, or featured snippet gained then lost
- **Action**: If featured snippet: add concise 40–60 word direct-answer paragraphs, definition-first structure

### Plateau-Then-Cliff
- **Shape**: Stable for months, sudden step-down, stable again at lower level
- **Diagnosis**: Core algorithm update (Google confirms ~4 per year)
- **Action**: Compare content quality/EAT signals vs. competitors who gained; update content depth

### Oscillation
- **Shape**: Position/clicks zigzag regularly (often weekly pattern)
- **Diagnosis**: SERP personalisation, device-split heavy keyword, or weekday vs. weekend intent shift
- **Action**: Segment by device; target the position range, not a single rank

---

## Keyword Intent Classification

| Intent | Signals | Optimisation target |
|--------|---------|---------------------|
| Informational | how, what, why, guide, tutorial | Impressions, `lostTop10` filter |
| Commercial | best, vs, review, compare, top | CTR (rich snippet structured data) |
| Transactional | buy, price, cheap, discount, order | Clicks, conversion — not just position |
| Navigational | brand name, login, site name | CTR only (dominate position 1) |

---

## Solution Comparison Template

When comparing two or more SEO solutions, produce this table:

| | Option A | Option B | Option C (if any) |
|--|----------|----------|-------------------|
| **What it does** | | | |
| **Pros** | | | |
| **Cons** | | | |
| **Risk** | Low/Med/High | | |
| **Time to effect** | | | |
| **Reversible?** | Yes/No | | |
| **Best for** | | | |

Then add: **Recommendation** — one winner with one-sentence justification using site-specific data. **Fallback** — what to do if the recommendation fails.

---

## Response Quality Checklist

Before finalising an analysis answer:
- [ ] Specific numbers cited (not just "position dropped")
- [ ] Root cause stated (not just "perform better")
- [ ] At least one actionable next step per problem identified
- [ ] Tradeoffs acknowledged if multiple solutions exist
- [ ] Date range and segment clearly stated so user can reproduce the finding
- [ ] Conclusion in plain language: "Bottom line: X is happening because Y, fix Z first"
- **Action**: Check Google Search Status Dashboard, Search Console coverage report, server logs for that date

---

## Position Tracking Interpretation

Average position in GSC is a weighted average across all impressions — it does not mean your page consistently ranks at that position. Understand the distribution:

- A page showing "position 5" may rank #1 for some queries and #20 for others
- High impression volume queries pull the average more than low-volume ones
- To get accurate ranking data for a single query, filter by that keyword specifically

**Tracking priority order**:
1. Branded keywords (protect at all costs)
2. High-volume transactional terms (revenue-generating)
3. High-volume informational terms (top-of-funnel)
4. Long-tail clusters (bulk opportunity, lower individual impact)
