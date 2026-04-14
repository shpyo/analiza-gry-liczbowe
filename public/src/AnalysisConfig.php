<?php
declare(strict_types=1);

/**
 * Centralized configuration for all analysis constants.
 *
 * Every numeric threshold, window size, or limit used in calculations
 * lives here — no magic numbers in page files.
 *
 * @see docs/stale-i-progi.md for detailed explanations of each value.
 */
final class AnalysisConfig
{
    // -----------------------------------------------------------------
    // Frequency analysis window
    // -----------------------------------------------------------------

    /** How many recent draws to use for frequency/trend analysis. */
    public const WINDOW_SIZE = 500;

    // -----------------------------------------------------------------
    // Temperature classification (hot/cold/stable)
    // Based on binomial model: μ ± Z_SCORE_THRESHOLD × σ
    // -----------------------------------------------------------------

    /** Z-score multiplier for hot/cold thresholds. 1.5 ≈ top/bottom 6.7%. */
    public const TEMPERATURE_Z_SCORE = 1.5;

    // -----------------------------------------------------------------
    // Overdue scoring
    // overdue_score = current_gap / avg_interval
    // -----------------------------------------------------------------

    /** Score at which a number is considered "moderately overdue". */
    public const OVERDUE_WARNING = 1.0;

    /** Score at which a number is considered "heavily overdue". */
    public const OVERDUE_CRITICAL = 2.0;

    // -----------------------------------------------------------------
    // Generator
    // -----------------------------------------------------------------

    /** Maximum iterations before giving up on filter-constrained generation. */
    public const GENERATOR_MAX_ATTEMPTS = 50_000;

    /** Maximum coupons per single generation request. */
    public const GENERATOR_MAX_COUPONS = 20;

    /** Default number of coupons to generate. */
    public const GENERATOR_DEFAULT_COUNT = 5;

    /** Available coupon count options in the UI dropdown. */
    public const GENERATOR_COUNT_OPTIONS = [1, 2, 3, 5, 10, 15, 20];

    /**
     * Weight formula addend: weight = window_freq + this value.
     * Ensures undrawn numbers (freq=0) still have a non-zero chance.
     */
    public const GENERATOR_WEIGHT_FLOOR = 1;

    /** How many top-frequency numbers form the "hot" group for filtering. */
    public const GENERATOR_TOP_HOT_COUNT = 10;

    // -----------------------------------------------------------------
    // Generator heatmap (visual thresholds, not statistical)
    // ratio = freq / max_freq
    // -----------------------------------------------------------------

    /** Ratio above which a cell is colored "hot" in the generator heatmap. */
    public const GENERATOR_HEATMAP_HOT_RATIO = 0.7;

    /** Ratio above which a cell is colored "warm" in the generator heatmap. */
    public const GENERATOR_HEATMAP_WARM_RATIO = 0.4;

    // -----------------------------------------------------------------
    // Profile popularity (validator)
    // -----------------------------------------------------------------

    /** Minimum pct_of_total to classify a profile as "popular". */
    public const PROFILE_POPULAR_PCT = 2.0;

    /** Minimum pct_of_total to classify a profile as "rare" (vs "very rare"). */
    public const PROFILE_RARE_PCT = 0.5;

    /** Multiplier for the popularity progress bar width (pct × this = bar %). */
    public const PROFILE_BAR_MULTIPLIER = 10;

    // -----------------------------------------------------------------
    // Heatmap (stats page, quintile-based)
    // -----------------------------------------------------------------

    /** Number of quintile groups for the stats heatmap. */
    public const HEATMAP_QUINTILE_COUNT = 5;

    // -----------------------------------------------------------------
    // Display: top-N numbers in UI cards
    // -----------------------------------------------------------------

    /** Hot numbers shown on the dashboard sidebar. */
    public const DISPLAY_DASHBOARD_HOT = 5;

    /** Cold numbers shown on the dashboard sidebar. */
    public const DISPLAY_DASHBOARD_COLD = 3;

    /** Hot numbers shown on the stats page sidebar. */
    public const DISPLAY_STATS_HOT = 4;

    /** Cold numbers shown on the stats page sidebar. */
    public const DISPLAY_STATS_COLD = 3;

    // -----------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------

    /** Draws per page in the history view. */
    public const DRAWS_PER_PAGE = 50;

    /** Pages shown before/after the current page in pagination. */
    public const PAGINATION_RANGE = 4;
}
