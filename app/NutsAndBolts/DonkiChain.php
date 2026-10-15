<?php

namespace App\NutsAndBolts;

/**
 * The story one event belongs to: every record reachable from it through DONKI's links, in
 * both directions, laid out oldest first. A flare, its CME, the CME's analysis, the model run,
 * the shock and the storm it caused all land in one chain. A linked record that was not loaded
 * still gets a place, with the type and time its id carries.
 */
final class DonkiChain
{
    /** The most records a chain shows; a storm season links hundreds, the nearest dozen tell the story. */
    public const int LIMIT = 12;

    /**
     * @param array<string, DonkiEvent> $events The loaded events, by id.
     * @param string $id The picked event's id.
     * @return list<array{id: string, type: string, time: int, label: string|null, loaded: bool, picked: bool, gap: int|null}>
     *         Oldest first; gap is the seconds since the record before it.
     */
    public static function around(array $events, string $id): array
    {
        $reverse = [];
        foreach ($events as $event) {
            foreach ($event->links as $link) {
                $reverse[$link][] = $event->id;
            }
        }

        $seen = [$id => true];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            $neighbours = [...($events[$current]->links ?? []), ...($reverse[$current] ?? [])];
            foreach ($neighbours as $neighbour) {
                if (! isset($seen[$neighbour]) && (isset($events[$neighbour]) || ! is_null(DonkiEvent::typeOfId($neighbour)))) {
                    $seen[$neighbour] = true;
                    $queue[] = $neighbour;
                }
            }
        }

        $cards = [];
        foreach (array_keys($seen) as $member) {
            $event = $events[$member] ?? null;
            $time = $event?->time ?? DonkiEvent::timeOfId($member);
            if (is_null($time)) {
                continue;
            }
            $cards[] = [
                'id' => $member,
                'type' => $event?->type ?? DonkiEvent::typeOfId($member),
                'time' => $time,
                'label' => $event?->label,
                'loaded' => ! is_null($event),
                'picked' => $member === $id,
                'gap' => null,
            ];
        }

        usort($cards, fn (array $a, array $b): int => $a['time'] <=> $b['time'] ?: strcmp($a['id'], $b['id']));
        $cards = self::nearest($cards);

        foreach ($cards as $i => &$card) {
            $card['gap'] = $i === 0 ? null : $card['time'] - $cards[$i - 1]['time'];
        }

        return $cards;
    }

    /**
     * At most LIMIT cards, keeping the picked one and those closest to it in time.
     * @param list<array<string, mixed>> $cards Oldest first.
     * @return list<array<string, mixed>>
     */
    protected static function nearest(array $cards): array
    {
        if (count($cards) <= self::LIMIT) {
            return $cards;
        }

        $picked = array_search(true, array_column($cards, 'picked'), true) ?: 0;
        $start = max(0, min($picked - intdiv(self::LIMIT, 2), count($cards) - self::LIMIT));

        return array_slice($cards, $start, self::LIMIT);
    }
}
