<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLog;
use App\Repositories\BookRepository;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookController
{
    public function __construct(
        private BookRepository $books,
        private AuditLog       $audit,
    ) {}

    public function index(Request $req, Response $res): Response
    {
        $params = $req->getQueryParams();
        $rows = $this->books->all(
            (string)($params['q']     ?? ''),
            (int)   ($params['limit'] ?? 0)
        );
        return $this->json($res, ['count' => count($rows), 'data' => $rows]);
    }

    public function show(Request $req, Response $res, array $args): Response
    {
        $book = $this->books->find((int)($args['id'] ?? 0));
        return $book
            ? $this->json($res, $book)
            : $this->json($res, ['error' => 'Not found'], 404);
    }

    public function create(Request $req, Response $res): Response
    {
        $body   = (array)($req->getParsedBody() ?? []);
        $errors = $this->validator()->validate($body);
        if ($errors) return $this->json($res, ['errors' => $errors], 400);

        $auth   = (array)$req->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);

        $id = $this->books->create($body, $userId);
        $this->audit->record($userId, 'book.create', 'books:' . $id, $this->ip($req));

        return $this->json($res, [
            'message' => 'Book created',
            'data'    => $this->books->find($id),
        ], 201)->withHeader('Location', '/api/books/' . $id);
    }

    public function update(Request $req, Response $res, array $args): Response
    {
        $id   = (int)($args['id'] ?? 0);
        $book = $this->books->find($id);
        if (!$book) return $this->json($res, ['error' => 'Not found'], 404);

        // IDOR check — only the creator OR an admin may update.
        $auth = (array)$req->getAttribute('auth', []);
        $isOwner = (int)$book['created_by'] === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';
        if (!$isOwner && !$isAdmin) {
            $this->audit->record((int)$auth['sub'], 'book.update.forbidden', 'books:' . $id, $this->ip($req));
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        $body   = (array)($req->getParsedBody() ?? []);
        $errors = $this->validator()->validate($body, partial: true);
        if ($errors) return $this->json($res, ['errors' => $errors], 400);

        $this->books->update($id, $body);
        $this->audit->record((int)$auth['sub'], 'book.update', 'books:' . $id, $this->ip($req));

        return $this->json($res, ['message' => 'Updated', 'data' => $this->books->find($id)]);
    }

    public function delete(Request $req, Response $res, array $args): Response
    {
        $auth = (array)$req->getAttribute('auth', []);
        if (($auth['role'] ?? 'member') !== 'admin') {
            return $this->json($res, ['error' => 'Admins only'], 403);
        }
        $id   = (int)($args['id'] ?? 0);
        $book = $this->books->find($id);
        if (!$book) return $this->json($res, ['error' => 'Not found'], 404);

        $this->books->delete($id);
        $this->audit->record((int)$auth['sub'], 'book.delete', 'books:' . $id, $this->ip($req));
        return $this->json($res, ['message' => 'Deleted', 'data' => $book]);
    }

    // -----------------------------------------------------------------
    private function validator(): Validator
    {
        return (new Validator())
            ->required('title', 'author', 'year')
            ->field('title',  Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year',   Validator::intRange(1000, (int)date('Y')), 'year must be 1000..current year')
            ->field('genre',  Validator::nonEmptyString(80),  'genre must be ≤ 80 chars');
    }

    private function ip(Request $r): string
    {
        return (string)($r->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function json(Response $res, mixed $data, int $status = 200): Response
    {
        $res->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));
        return $res
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
