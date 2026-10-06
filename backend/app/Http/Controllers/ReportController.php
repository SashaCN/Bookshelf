<?php

namespace App\Http\Controllers;

use App\Actions\Social\ReportContent;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    /**
     * Report a post. Three open reports from different readers hide it until a moderator looks at it.
     */
    public function post(StoreReportRequest $request, Post $post, ReportContent $reports): JsonResponse
    {
        return $this->respond($reports, $request, $post);
    }

    public function comment(StoreReportRequest $request, Comment $comment, ReportContent $reports): JsonResponse
    {
        return $this->respond($reports, $request, $comment);
    }

    private function respond(ReportContent $reports, StoreReportRequest $request, Post|Comment $content): JsonResponse
    {
        /** @var array{reason: string, note?: string|null} $data */
        $data = $request->validated();

        $report = $reports->handle($request->user(), $content, $data);

        return (new ReportResource($report))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
