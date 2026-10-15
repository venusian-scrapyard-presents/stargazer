<?php

namespace App\Services\DONKI;

use App\Concerns\DONKI\DONKIContent;
use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\DonkiChain;
use App\NutsAndBolts\DonkiEvent;
use App\NutsAndBolts\DonkiRows;
use App\NutsAndBolts\DonkiTimeline;
use App\NutsAndBolts\Pretty;
use App\Services\NasaService;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\NutsAndBolts\Color;
use Voyager\NutsAndBolts\Collection;

/**
 * DONKI space weather: one event type, or every type swept in turn, over a date range, drawn as
 * a timeline of lanes with the links between records; clicking a record lays out its chain.
 */
class DONKIService extends NasaService
{
    use DONKIContent;

    protected const string ALL = 'All event types';

    /** @var list<DonkiEvent> In table order. */
    protected array $events = [];

    /** @var array<string, DonkiEvent> The same events by id (the first of an id wins). */
    protected array $by_id = [];

    /** @var array{0: int, 1: int}|null The loaded range as unix times, from and to. */
    protected ?array $range = null;

    protected ?DonkiTimeline $timeline = null;

    protected ?Framebuffer $framebuffer = null;

    protected ?RenderingEngine $velvet = null;

    protected bool $dirty = false;

    protected ?string $picked = null;

    public function key(): string
    {
        return 'donki';
    }

    public function mounted(TKColumn $root): void
    {
        $this->setMainComponents($root);
    }

    public function from(): ?string
    {
        return $this->from->date()?->format('Y-m-d');
    }

    public function to(): ?string
    {
        return $this->to->date()?->format('Y-m-d');
    }

    /**
     * The picked type, or every type for All event types.
     * @return list<string> DonkiRows::TYPES keys.
     */
    public function types(): array
    {
        $types = array_keys(DonkiRows::TYPES);

        return $this->type->selected() === 0 ? $types : [$types[$this->type->selected() - 1]];
    }

    public function isLoad(string $uuid): bool
    {
        return $uuid === $this->load?->uuid();
    }

    public function isList(string $uuid): bool
    {
        return $uuid === $this->list?->uuid();
    }

    public function isEventsTable(string $uuid): bool
    {
        return $uuid === $this->events_table?->uuid();
    }

    public function isTimeline(string $uuid): bool
    {
        return $uuid === $this->timeline_view?->uuid();
    }

    public function clearEvents(): void
    {
        $this->events = [];
        $this->by_id = [];
        $this->picked = null;
        $this->events_table->clearRows();
        $this->detail->setValue('');
        $this->clearChain();
        $this->timeline = null;
        $this->repaint();
    }

    /**
     * @param string $type A DonkiRows::TYPES key.
     * @param Collection<int, object> $found That type's DTOs.
     */
    public function appendEvents(string $type, Collection $found): void
    {
        foreach ($found as $record) {
            $event = DonkiEvent::from($type, $record);
            $this->events[] = $event;
            $this->by_id[$event->id] ??= $event;
            $this->events_table->appendRow(DonkiRows::row($type, $record));
        }
    }

    /**
     * Sort what the sweep found newest first, draw the timeline, and record the outcome.
     * @param list<string> $types
     */
    public function finishEvents(GlobalVariables $bag, array $types, string $from, string $to): void
    {
        $failed = $bag->failed ?? [];
        if (count($failed) === count($types)) {
            $bag->outcome = isset($bag->rate_limited)
                ? [Outcome::RATE_LIMITED, $bag->rate_limited]
                : [Outcome::FAILED, 'Every event type failed: '.implode(', ', $failed).(isset($bag->error) ? " ({$bag->error})" : '').'.'];

            return;
        }

        // Newest first, across every type.
        usort($this->events, fn (DonkiEvent $a, DonkiEvent $b): int => $b->time <=> $a->time);
        $this->events_table->setRows(array_map(fn (DonkiEvent $event): array => DonkiRows::row($event->type, $event->record), $this->events));
        $this->range = [DonkiEvent::stamp($from.'Z') ?? 0, DonkiEvent::stamp($to.'Z') ?? 0];
        $this->layoutTimeline();

        $linked = count(array_filter($this->events, fn (DonkiEvent $event): bool => $event->links !== []));
        $note = match (true) {
            $failed === [] => '',
            isset($bag->rate_limited) => ' ('.implode(', ', $failed)." not loaded: {$bag->rate_limited})",
            default => ' ('.implode(', ', $failed).' failed)',
        };
        $this->events === []
            ? $this->empty($bag, "No events between {$from} and {$to}{$note}.")
            : $this->done($bag, sprintf('%d events across %d type%s, %s to %s; %d linked to others%s.', count($this->events), count($types), count($types) === 1 ? '' : 's', $from, $to, $linked, $note));
    }

    /**
     * A table row picked: that event.
     */
    public function showDetail(?int $row): bool
    {
        if (! is_null($row) && isset($this->events[$row])) {
            $this->pick($this->events[$row]);
        }

        return true;
    }

    /**
     * A click on the timeline, in points: the event nearest it.
     */
    public function pickAt(float $x, float $y): bool
    {
        if (is_null($this->timeline)) {
            return true;
        }

        $scale = $this->pixelScale();
        $index = $this->timeline->nearest($x * $scale, $y * $scale);
        if (! is_null($index) && isset($this->events[$index])) {
            $this->pick($this->events[$index]);
            $this->events_table->selectRow($index);
        }

        return true;
    }

    /**
     * Show or hide the table of every event.
     */
    public function showList(bool $on): bool
    {
        $this->events_table->setVisible($on);

        return true;
    }

    /**
     * Every sketch tick: follow the canvas's size, and show what changed since the last one.
     */
    public function tick(): void
    {
        [$width, $height] = $this->timeline_view->pixelSize();
        if ($width < 1 || $height < 1) {
            return;
        }

        $framebuffer = $this->timeline_view->framebuffer('full');
        if ($framebuffer !== $this->framebuffer) {
            $this->framebuffer = $framebuffer;
            $this->velvet = app('drawing')->renderer('velvet', ['framebuffer' => $framebuffer]);
            $this->layoutTimeline();
        }

        if ($this->dirty) {
            $this->dirty = false;
            $this->timeline_view->present();
        }
    }

    /**
     * The picked event: ringed on the timeline, its chain laid out, its record shown as JSON.
     */
    protected function pick(DonkiEvent $event): void
    {
        $this->picked = $event->id;
        $this->detail->setValue(Pretty::json($event->record));
        $this->layoutChain(DonkiChain::around($this->by_id, $event->id));
        $this->repaint();
    }

    /**
     * @param list<array{id: string, type: string, time: int, label: string|null, loaded: bool, picked: bool, gap: int|null}> $cards
     */
    protected function layoutChain(array $cards): void
    {
        $this->clearChain();
        foreach ($cards as $i => $card) {
            if ($i > 0) {
                $gap = $this->chain->label("gap-{$i}", '→ +'.DonkiEvent::span($card['gap']));
                $gap->setTextColor(Color::hex('#8a8a8a'))->align(Align::CENTER, Align::CENTER);
            }

            $column = $this->chain->column("card-{$i}", 2, 6);
            $type = $column->label('type', $card['type'].($card['picked'] ? ' ◀' : ''));
            $type->setFont(new FontSpec(13, FontWeight::BOLD))->setTextColor(Color::hex(DonkiTimeline::COLORS[$card['type']] ?? '#c9c9d1'));
            $column->label('time', gmdate('M j H:i', $card['time']).' UTC');
            $column->label('label', $card['loaded'] ? ($card['label'] === '' ? '—' : $card['label']) : 'not in this range')
                ->setTextColor(Color::hex($card['loaded'] ? '#c9c9d1' : '#8a8a8a'));
        }

        $this->chain->setVisible($cards !== []);
    }

    protected function clearChain(): void
    {
        foreach ($this->chain->children() as $child) {
            $child->remove();
        }
        $this->chain->hide();
    }

    /**
     * Lay the loaded events out over the canvas's pixels, and paint them.
     */
    protected function layoutTimeline(): void
    {
        if (is_null($this->framebuffer) || is_null($this->range)) {
            return;
        }

        [$width, $height] = $this->timeline_view->pixelSize();
        $this->timeline = new DonkiTimeline($this->events, $this->range[0], $this->range[1], $width, $height, $this->pixelScale());
        $this->repaint();
    }

    protected function repaint(): void
    {
        if (is_null($this->velvet)) {
            return;
        }

        $font = app('fonts')->face();
        $timeline = $this->timeline;
        $this->velvet->frame(function (RenderingEngine $g) use ($timeline, $font): void {
            if (is_null($timeline)) {
                $g->clear(Color::hex('#17171c'));

                return;
            }
            $timeline->paint($g, $font, $this->picked);
        });
        $this->dirty = true;
    }

    /** Device pixels per point of the canvas. */
    protected function pixelScale(): float
    {
        [$pixels] = $this->timeline_view->pixelSize();
        [$points] = $this->timeline_view->size() + [0];

        return $points > 0 ? $pixels / $points : 1.0;
    }

    protected function setBusy(bool $busy): void
    {
        $this->load->setEnabled(! $busy);
    }
}
