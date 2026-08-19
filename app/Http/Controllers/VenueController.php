<?php

namespace App\Http\Controllers;

use App\Helpers\CongestionHelper;
use App\Models\Venue;
use App\Support\ContentModeration;
use App\Support\LineMessaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VenueController extends Controller
{
    /** 1ページに載せる施設の数。 */
    private const PER_PAGE = 60;

    public function index(Request $request)
    {
        // 旧URL（/?area=東京都）は都道府県ページへ送る。
        if ($request->filled('area')) {
            $slug = Venue::slugForArea((string) $request->input('area'));

            if ($slug !== null) {
                return redirect()->route('venues.area', ['areaSlug' => $slug], 301);
            }
        }

        // 5,000件を超える一覧を1ページに描くと、HTMLだけで6MBになる。
        // 表示・地図ともにこのページの分だけにする。
        $venues = Venue::query()->latest()->paginate(self::PER_PAGE);

        return $this->listView($venues, null, null, Venue::count());
    }

    /**
     * 都道府県ページ（/area/tokyo）と、施設種別まで絞ったページ（/area/tokyo/sauna）。
     *
     * 「東京都 サウナ」のように探す人が多く、トップページ1枚では受けきれない。
     */
    public function area(string $areaSlug, ?string $typeSlug = null)
    {
        $area = Venue::areaForSlug($areaSlug);
        $type = $typeSlug === null ? null : Venue::typeForSlug($typeSlug);

        if ($area === null || ($typeSlug !== null && $type === null)) {
            abort(404);
        }

        $venues = Venue::where('area', $area)
            ->when($type, fn ($query) => $query->where('facility_type', $type))
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        // 中身の無いページを作らない。
        if ($venues->total() === 0) {
            abort(404);
        }

        return $this->listView($venues, $area, $type, $venues->total());
    }

    private function listView($venues, ?string $area, ?string $type, int $total)
    {
        return view('venues.index', [
            'venues' => $venues,
            'areaCounts' => $this->areaCounts(),
            'typeCounts' => $area === null ? collect() : $this->typeCounts($area),
            'area' => $area,
            'areaSlug' => Venue::slugForArea($area),
            'type' => $type,
            'typeSlug' => Venue::slugForType($type),
            'total' => $total,
        ]);
    }

    /** 都道府県ごとの掲載件数（多い順）。 */
    private function areaCounts()
    {
        return Venue::query()
            ->selectRaw('area, COUNT(*) as total')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'area' => $row->area,
                'slug' => Venue::slugForArea($row->area),
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row) => $row['slug'] !== null)
            ->values();
    }

    /** その都道府県での施設種別ごとの件数。 */
    private function typeCounts(string $area)
    {
        return Venue::query()
            ->selectRaw('facility_type, COUNT(*) as total')
            ->where('area', $area)
            ->whereNotNull('facility_type')
            ->groupBy('facility_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->facility_type,
                'slug' => Venue::slugForType($row->facility_type),
                'total' => (int) $row->total,
            ])
            ->filter(fn (array $row) => $row['slug'] !== null)
            ->values();
    }

    public function create()
    {
        return view('venues.create');
    }

    public function store(Request $request)
    {
        if (! empty($request->input('website'))) {
            return redirect()->route('venues.thanks');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'facility_type' => 'nullable|string|max:20',
            'area' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if (ContentModeration::containsNgWord($validated['name'] . ' ' . ($validated['description'] ?? ''))) {
            return back()->withErrors(['name' => '投稿内容に使用できない文字列が含まれています。'])->withInput();
        }

        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("venue-create:{$ipHash}", 30)) {
            return back()->withErrors(['name' => '投稿間隔が短すぎます。しばらく待ってから再度お試しください。'])->withInput();
        }

        Venue::create($validated);

        return redirect()->route('venues.thanks');
    }

    public function show(Venue $venue)
    {
        $venue->load(['reviews' => fn ($q) => $q->latest()]);

        $isFavorited = session('line_user_local_id')
            ? $venue->favorites()->where('line_user_id', session('line_user_local_id'))->exists()
            : false;

        return view('venues.show', compact('venue', 'isFavorited'));
    }

    public function reportCongestion(Request $request, Venue $venue)
    {
        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("congestion:{$venue->id}:{$ipHash}", 60)) {
            return response()->json(['error' => '報告間隔が短すぎます。しばらく待ってから再度お試しください。'], 429);
        }

        $validated = $request->validate([
            'level' => 'required|in:empty,slightly_crowded,crowded,very_crowded',
        ]);

        $levelMap = ['empty' => 1, 'slightly_crowded' => 2, 'crowded' => 3, 'very_crowded' => 4];
        $numericLevel = $levelMap[$validated['level']];

        $previousBucket = CongestionHelper::getText($venue->average_congestion);

        $reports = $venue->congestion_reports ?? [];
        $reports[] = $numericLevel;
        $average = array_sum($reports) / count($reports);

        $venue->congestion_reports = $reports;
        $venue->average_congestion = round($average, 2);
        $venue->save();

        $newBucket = CongestionHelper::getText($venue->average_congestion);
        if ($newBucket !== $previousBucket) {
            $this->notifyFavoritesOfCongestionChange($venue, $newBucket);
        }

        return response()->json(['average_congestion' => $venue->average_congestion]);
    }

    private function notifyFavoritesOfCongestionChange(Venue $venue, string $newBucket): void
    {
        $venue->loadMissing('favorites.lineUser');

        foreach ($venue->favorites as $favorite) {
            if (! $favorite->lineUser) {
                continue;
            }

            LineMessaging::push(
                $favorite->lineUser->line_user_id,
                "「{$venue->name}」の混雑状況が「{$newBucket}」に変わりました。"
            );
        }
    }

    public function like(Request $request, Venue $venue)
    {
        $ipHash = ContentModeration::clientIpHash($request);
        if (ContentModeration::isTooSoon("like:{$venue->id}:{$ipHash}", 60)) {
            return response()->json(['error' => 'いいね！は少し時間を空けてから再度お試しください。'], 429);
        }

        $venue->increment('likes_count');
        $venue->refresh();

        return response()->json(['likes_count' => $venue->likes_count]);
    }

    public function sitemap()
    {
        // 5,000件を超えるので、毎回組み立てると重い。短時間だけ覚えておく。
        $xml = Cache::remember('sitemap-xml', now()->addHour(), function () {
            $venues = Venue::select('id', 'updated_at')->get();

            // 掲載のある都道府県ページと、その中の施設種別ページを載せる。
            $areaUrls = Venue::query()
                ->selectRaw('area, facility_type')
                ->whereNotNull('area')
                ->groupBy('area', 'facility_type')
                ->get()
                ->flatMap(function ($row) {
                    $areaSlug = Venue::slugForArea($row->area);
                    $typeSlug = Venue::slugForType($row->facility_type);

                    if ($areaSlug === null) {
                        return [];
                    }

                    return array_filter([
                        route('venues.area', $areaSlug),
                        $typeSlug ? route('venues.area.type', [$areaSlug, $typeSlug]) : null,
                    ]);
                })
                ->unique()
                ->sort()
                ->values();

            return view('sitemap', compact('venues', 'areaUrls'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
