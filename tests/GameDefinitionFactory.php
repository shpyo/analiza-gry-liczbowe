<?php
declare(strict_types=1);

/**
 * Factory for creating GameDefinition instances in tests without a database.
 */
final class GameDefinitionFactory
{
    public static function lotto(): GameDefinition
    {
        return new GameDefinition(
            slug: 'lotto',
            name: 'Lotto',
            pickCount: 6,
            poolSize: 49,
            lowThreshold: 24,
            hasBonus: false,
            syncUrl: null,
            drawsTable: 'lotto_draws',
            profileTable: 'lotto_draw_profiles',
            sumBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 79,   'description' => 'bardzo mała (21–79)'],
                ['label' => 'S',  'max' => 109,  'description' => 'mała (80–109)'],
                ['label' => 'M',  'max' => 170,  'description' => 'średnia (110–170)'],
                ['label' => 'L',  'max' => 200,  'description' => 'duża (171–200)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (201–279)'],
            ]),
            rangeBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 19,   'description' => 'bardzo mały (0–19)'],
                ['label' => 'S',  'max' => 29,   'description' => 'mały (20–29)'],
                ['label' => 'M',  'max' => 39,   'description' => 'średni (30–39)'],
                ['label' => 'L',  'max' => 44,   'description' => 'duży (40–44)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (45–48)'],
            ]),
            lineParser: new MbnetLineParser(6, false),
        );
    }

    public static function miniLotto(): GameDefinition
    {
        return new GameDefinition(
            slug: 'mini_lotto',
            name: 'Mini Lotto',
            pickCount: 5,
            poolSize: 42,
            lowThreshold: 21,
            hasBonus: false,
            syncUrl: null,
            drawsTable: 'mini_lotto_draws',
            profileTable: 'mini_lotto_draw_profiles',
            sumBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 49,   'description' => 'bardzo mała (≤49)'],
                ['label' => 'S',  'max' => 79,   'description' => 'mała (50–79)'],
                ['label' => 'M',  'max' => 120,  'description' => 'średnia (80–120)'],
                ['label' => 'L',  'max' => 159,  'description' => 'duża (121–159)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (160+)'],
            ]),
            rangeBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 12,   'description' => 'bardzo mały (≤12)'],
                ['label' => 'S',  'max' => 22,   'description' => 'mały (13–22)'],
                ['label' => 'M',  'max' => 31,   'description' => 'średni (23–31)'],
                ['label' => 'L',  'max' => 37,   'description' => 'duży (32–37)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (38+)'],
            ]),
            lineParser: new MbnetLineParser(5, false),
        );
    }

    public static function lottoPlus(): GameDefinition
    {
        return new GameDefinition(
            slug: 'lotto_plus',
            name: 'Lotto Plus',
            pickCount: 6,
            poolSize: 49,
            lowThreshold: 24,
            hasBonus: true,
            syncUrl: null,
            drawsTable: 'lotto_plus_draws',
            profileTable: 'lotto_plus_draw_profiles',
            sumBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 79,   'description' => 'bardzo mała (21–79)'],
                ['label' => 'S',  'max' => 109,  'description' => 'mała (80–109)'],
                ['label' => 'M',  'max' => 170,  'description' => 'średnia (110–170)'],
                ['label' => 'L',  'max' => 200,  'description' => 'duża (171–200)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (201–279)'],
            ]),
            rangeBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 19,   'description' => 'bardzo mały (0–19)'],
                ['label' => 'S',  'max' => 29,   'description' => 'mały (20–29)'],
                ['label' => 'M',  'max' => 39,   'description' => 'średni (30–39)'],
                ['label' => 'L',  'max' => 44,   'description' => 'duży (40–44)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (45–48)'],
            ]),
            lineParser: new MbnetLineParser(6, true),
        );
    }

    public static function multiMulti(): GameDefinition
    {
        return new GameDefinition(
            slug: 'multi_multi',
            name: 'Multi Multi',
            pickCount: 20,
            poolSize: 80,
            lowThreshold: 40,
            hasBonus: false,
            syncUrl: null,
            drawsTable: 'multi_multi_draws',
            profileTable: 'multi_multi_draw_profiles',
            sumBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 675,  'description' => 'bardzo mała (≤675)'],
                ['label' => 'S',  'max' => 745,  'description' => 'mała (676–745)'],
                ['label' => 'M',  'max' => 875,  'description' => 'średnia (746–875)'],
                ['label' => 'L',  'max' => 945,  'description' => 'duża (876–945)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (946+)'],
            ]),
            rangeBuckets: new ThresholdBucketStrategy([
                ['label' => 'XS', 'max' => 62,   'description' => 'bardzo mały (≤62)'],
                ['label' => 'S',  'max' => 70,   'description' => 'mały (63–70)'],
                ['label' => 'M',  'max' => 75,   'description' => 'średni (71–75)'],
                ['label' => 'L',  'max' => 78,   'description' => 'duży (76–78)'],
                ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (79+)'],
            ]),
            lineParser: new MbnetLineParser(20, false),
        );
    }
}
