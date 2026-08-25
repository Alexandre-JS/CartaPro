<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Services\PackagePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Geração e publicação do pacote offline.
 *
 * Mudança de segurança: o ficheiro deixou de ser escrito em
 * `public/packages/` (servido pelo webserver a quem o pedisse, com a resposta
 * correta e a explicação de todas as perguntas). Passa a viver no disco
 * privado e só é servido pela rota autenticada de download do admin.
 */
class PublicationController extends Controller
{
    private const DISK = PackagePublisher::DISK;

    public function __construct(private readonly PackagePublisher $publisher) {}

    public function index(): View
    {
        return view('admin.publications.index', [
            'approvedCount' => Question::where('status', 'approved')->where('is_active', true)->count(),
            'byTopic' => Topic::where('is_active', true)->withCount(['questions' => fn ($query) => $query->where('status', 'approved')->where('is_active', true)])->orderBy('sort_order')->get(),
            'packages' => ContentPackage::with('publisher')->latest('published_at')->paginate(10),
            'publishedExamsCount' => Exam::where(['is_public' => true, 'is_active' => true, 'publication_status' => 'published'])->count(),
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $package = $this->publisher->publish($request->user(), $data['notes'] ?? null);

        return back()->with('status', 'Pacote '.$package->version.' publicado com sucesso.');
    }

    public function restore(Request $request, ContentPackage $package): RedirectResponse
    {
        ContentPackage::where('status', 'published')->update(['status' => 'archived']);
        $package->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);

        return back()->with('status', 'Pacote '.$package->version.' restaurado.');
    }

    /** Download autenticado (admin). O ficheiro não é acessível por URL direta. */
    public function download(ContentPackage $package): StreamedResponse
    {
        $path = $package->file_path ? ltrim($package->file_path, '/') : null;

        if ($path && Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->download($path, basename($path));
        }

        // Pacotes anteriores à mudança de disco: serve o payload guardado.
        return response()->streamDownload(function () use ($package): void {
            echo json_encode($package->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'prontovia-'.$package->version.'.json', ['Content-Type' => 'application/json']);
    }

}
