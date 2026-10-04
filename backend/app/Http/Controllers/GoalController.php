<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetGoalRequest;
use App\Services\Stats\GoalProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoalController extends Controller
{
    /**
     * The goal of a year and how the reader stands against it. A year without a goal still reports the books finished.
     */
    public function show(Request $request, int $year, GoalProgress $progress): JsonResponse
    {
        return response()->json(['data' => $progress->handle($request->user(), $year)]);
    }

    /**
     * Set the goal "N books" for a year, or change it.
     */
    public function update(SetGoalRequest $request, int $year, GoalProgress $progress): JsonResponse
    {
        $request->user()->readingGoals()->updateOrCreate(
            ['year' => $year],
            ['target_books' => $request->integer('target_books')],
        );

        return response()->json(['data' => $progress->handle($request->user(), $year)]);
    }

    public function destroy(Request $request, int $year): Response
    {
        $request->user()->readingGoals()->where('year', $year)->delete();

        return response()->noContent();
    }
}
