<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ferry extends Model
{
    use HasFactory;

    /** Grid cell codes. A deck row is a string of these. */
    public const CELL_SEAT = 'S';
    public const CELL_EMPTY = '.';

    /** Where people board. Bow/stern index a column; port/starboard a row. */
    public const EDGES = ['bow', 'stern', 'port', 'starboard'];

    /** Columns in the generated fallback deck: four, an aisle, four. */
    private const FALLBACK_ROW = 'SSSS.SSSS';
    private const FALLBACK_SEATS_PER_ROW = 8;

    protected $fillable = [
        'name',
        'capacity',
        'price_per_seat',
        'layout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_seat' => 'decimal:2',
            'layout' => 'array',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(FerrySchedule::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(FerryScheduleTemplate::class);
    }

    /**
     * The deck plan, normalised for rendering: a rectangular grid, the boarding
     * points, and every seat resolved to its number and grid position.
     *
     * This is now the single definition of how a seat number maps to a place on
     * the boat. The visitor seat picker and the gate validation screen each used
     * to derive that themselves from `capacity` - two copies of one assumption,
     * which is the reason a configurable layout could not exist.
     *
     * @return array{grid: list<string>, rows: int, columns: int, entrances: list<array>, seats: list<array{number:int,row:int,column:int}>, capacity: int}
     */
    public function deck(): array
    {
        $layout = is_array($this->layout) ? $this->layout : [];
        $grid = self::normaliseGrid($layout['grid'] ?? null) ?? $this->fallbackGrid();
        $columns = $grid === [] ? 0 : strlen($grid[0]);

        // Row-major over seat cells only. The fallback grid is 8 wide with the
        // aisle in the middle, so row 1 numbers 1-8 and row 2 numbers 9-16 -
        // identical to what the hardcoded renderers produced.
        $seats = [];
        $number = 0;
        foreach ($grid as $rowIndex => $row) {
            foreach (str_split($row) as $columnIndex => $cell) {
                if ($cell !== self::CELL_SEAT) {
                    continue;
                }
                $seats[] = [
                    'number' => ++$number,
                    'row' => $rowIndex + 1,
                    'column' => $columnIndex + 1,
                ];
            }
        }

        return [
            'grid' => $grid,
            'rows' => count($grid),
            'columns' => $columns,
            'entrances' => self::normaliseEntrances($layout['entrances'] ?? null, count($grid), $columns),
            'seats' => $seats,
            'capacity' => $number,
        ];
    }

    /** Seats the grid actually contains, which is what `capacity` must equal. */
    public static function seatCount(array $grid): int
    {
        return array_sum(array_map(
            fn (string $row) => substr_count($row, self::CELL_SEAT),
            $grid
        ));
    }

    /**
     * Rejects anything that isn't a rectangle of known cell codes, so a
     * malformed layout can't be stored and then blow up at render time.
     *
     * @return list<string>|null
     */
    public static function normaliseGrid(mixed $grid): ?array
    {
        if (! is_array($grid) || $grid === []) {
            return null;
        }

        $rows = [];
        $width = null;

        foreach ($grid as $row) {
            if (! is_string($row) || $row === '') {
                return null;
            }
            $width ??= strlen($row);
            if (strlen($row) !== $width) {
                return null;
            }
            if (preg_match('/[^'.preg_quote(self::CELL_SEAT.self::CELL_EMPTY, '/').']/', $row)) {
                return null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{edge:string,index:int,label:string|null}>
     */
    public static function normaliseEntrances(mixed $entrances, int $rows, int $columns): array
    {
        if (! is_array($entrances)) {
            return [];
        }

        $clean = [];
        foreach ($entrances as $entrance) {
            if (! is_array($entrance)) {
                continue;
            }
            $edge = $entrance['edge'] ?? null;
            $index = $entrance['index'] ?? null;
            if (! in_array($edge, self::EDGES, true) || ! is_numeric($index)) {
                continue;
            }

            // Bow and stern sit along a column; port and starboard along a row.
            $limit = in_array($edge, ['bow', 'stern'], true) ? $columns : $rows;
            $index = (int) $index;
            if ($index < 1 || $index > $limit) {
                continue;
            }

            $label = $entrance['label'] ?? null;
            $clean[] = [
                'edge' => $edge,
                'index' => $index,
                'label' => is_string($label) && $label !== '' ? mb_substr($label, 0, 40) : null,
            ];
        }

        // One door per spot, so a duplicate is a no-op rather than two markers
        // stacked on the same cell.
        $keyed = [];
        foreach ($clean as $entrance) {
            $keyed[$entrance['edge'].':'.$entrance['index']] = $entrance;
        }

        return array_values($keyed);
    }

    /**
     * Four-plus-aisle-plus-four for `capacity` seats, filling the last row
     * partially - exactly what the hardcoded renderers drew before layouts
     * existed. Used for any ferry whose layout has never been edited.
     *
     * @return list<string>
     */
    private function fallbackGrid(): array
    {
        $capacity = max(0, (int) $this->capacity);
        $rowCount = (int) ceil($capacity / self::FALLBACK_SEATS_PER_ROW);
        $grid = [];

        for ($row = 0; $row < $rowCount; $row++) {
            $remaining = $capacity - ($row * self::FALLBACK_SEATS_PER_ROW);
            if ($remaining >= self::FALLBACK_SEATS_PER_ROW) {
                $grid[] = self::FALLBACK_ROW;

                continue;
            }

            // Partial final row: keep the seats that exist, blank the rest, and
            // leave the aisle where it is so the row still lines up above it.
            $cells = str_split(self::FALLBACK_ROW);
            $kept = 0;
            foreach ($cells as $i => $cell) {
                if ($cell !== self::CELL_SEAT) {
                    continue;
                }
                if ($kept < $remaining) {
                    $kept++;
                } else {
                    $cells[$i] = self::CELL_EMPTY;
                }
            }
            $grid[] = implode('', $cells);
        }

        return $grid;
    }
}
