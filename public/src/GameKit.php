<?php
declare(strict_types=1);

final class GameKit
{
    private readonly GameRegistry       $registry;
    private readonly MetricCalculator   $calculator;
    private readonly ProfileDescriber   $describer;
    private readonly MetricTextProvider $texts;
    private readonly DrawRepository     $repository;
    private readonly CoOccurrenceRepository $coOccurrenceRepo;

    public function __construct(PDO $pdo)
    {
        $this->registry         = new GameRegistry($pdo);
        $this->calculator       = new MetricCalculator();
        $this->describer        = new ProfileDescriber();
        $this->texts            = new MetricTextProvider();
        $this->repository       = new DrawRepository($pdo);
        $this->coOccurrenceRepo = new CoOccurrenceRepository($pdo);
    }

    /** Get a GameDefinition by slug. */
    public function game(string $slug): GameDefinition
    {
        return $this->registry->get($slug);
    }

    /** Resolve game from $_GET['game'] with fallback to 'lotto'. */
    public function gameFromRequest(): GameDefinition
    {
        $slug = isset($_GET['game']) ? trim($_GET['game']) : 'lotto';
        $allSlugs = $this->registry->allSlugs();
        if (!in_array($slug, $allSlugs, true)) {
            $slug = 'lotto';
        }
        return $this->registry->get($slug);
    }

    public function calculator(): MetricCalculator
    {
        return $this->calculator;
    }

    public function describer(): ProfileDescriber
    {
        return $this->describer;
    }

    public function texts(): MetricTextProvider
    {
        return $this->texts;
    }

    public function repository(): DrawRepository
    {
        return $this->repository;
    }

    public function registry(): GameRegistry
    {
        return $this->registry;
    }

    public function coOccurrence(): CoOccurrenceRepository
    {
        return $this->coOccurrenceRepo;
    }
}
