<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'author' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(Book::STATUSES)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Buku selalu dibatasi milik user yang login; milik orang lain => 404. */
    private function findOwned(Request $request, int $id): Book
    {
        return $request->user()->books()->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->books()->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $book = $request->user()->books()->create($request->validate($this->rules()));

        // Hanya id dan status; judul, penulis, dan catatan tidak dicatat.
        Log::info('book.created', [
            'actor' => $request->user()->id,
            'action' => 'menyimpan buku baru',
            'cause' => 'pengguna menambah buku ke koleksinya',
            'book_id' => $book->id,
            'status' => $book->status,
        ]);

        return response()->json($book, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->findOwned($request, $id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $book = $this->findOwned($request, $id);
        $book->update($request->validate($this->rules()));

        return response()->json($book);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->findOwned($request, $id)->delete();

        Log::info('book.deleted', [
            'actor' => $request->user()->id,
            'action' => 'menghapus buku',
            'cause' => 'pengguna menghapus buku miliknya',
            'book_id' => $id,
        ]);

        return response()->json(['message' => 'Buku dihapus.']);
    }
}