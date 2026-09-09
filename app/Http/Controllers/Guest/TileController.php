<?php

namespace App\Http\Controllers\Guest;

use App\Enums\HomeTileAction;
use App\Http\Controllers\Controller;
use App\Models\HomeTile;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A tile's own page, and the two files behind it.
 *
 * Both files live on the private disk and are served through here rather than
 * from a public link, which is what keeps them tenant-checked: a tile is only
 * reachable through the restaurant it belongs to, and only while that
 * restaurant is trading.
 */
class TileController extends Controller
{
    /**
     * The page a PDF tile opens.
     *
     * The PDF is embedded rather than downloaded, so the app keeps its own
     * header and the guest has a back arrow out of it. A file that opened in
     * the browser's own viewer would strand them with no way back to the menu
     * but the phone's own gesture.
     */
    public function show(Restaurant $restaurant, HomeTile $tile): Response
    {
        $this->authoriseTile($restaurant, $tile);

        abort_unless($tile->action === HomeTileAction::Pdf, 404);

        return Inertia::render('document', [
            'title' => $tile->label,
            'documentUrl' => route('guest.tiles.document.show', [
                'restaurant' => $restaurant->slug,
                'tile' => $tile->getKey(),
            ]),
        ]);
    }

    /**
     * The tile's picture.
     */
    public function image(Restaurant $restaurant, HomeTile $tile): StreamedResponse
    {
        $this->authoriseTile($restaurant, $tile);

        return $this->stream($tile->image_path);
    }

    /**
     * The tile's PDF.
     */
    public function document(Restaurant $restaurant, HomeTile $tile): StreamedResponse
    {
        $this->authoriseTile($restaurant, $tile);

        // Inline rather than as an attachment: this is embedded in the page
        // above, and a download would be a file the guest has to go and find.
        return $this->stream($tile->document_path, inline: true);
    }

    /**
     * Refuse a tile that is not this restaurant's, or not on show.
     *
     * The restaurant arrives from the subdomain rather than from the path, so
     * Laravel's scoped bindings do not cover it and the check is made here.
     * Without it, editing the id in the URL would read another restaurant's
     * files off the same disk.
     */
    private function authoriseTile(Restaurant $restaurant, HomeTile $tile): void
    {
        abort_unless($restaurant->is_active, 404);
        abort_unless($tile->restaurant_id === $restaurant->getKey(), 404);
        abort_unless($tile->is_active, 404);
    }

    /**
     * Send a file from the private disk, or 404 when it has gone.
     */
    private function stream(?string $path, bool $inline = false): StreamedResponse
    {
        abort_if(blank($path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response(
            $path,
            headers: $inline ? ['Content-Disposition' => 'inline'] : [],
        );
    }
}
