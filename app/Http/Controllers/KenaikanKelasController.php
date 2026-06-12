<?php

namespace App\Http\Controllers;

use App\Services\Modules\PromotionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KenaikanKelasController extends PageActionController
{
    public function __construct(
        protected PromotionWorkflowService $promotionWorkflows,
    ) {
    }

    public function index(): View
    {
        return view('pages.kenaikan-kelas');
    }

    public function grade(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->promotionWorkflows->processGradePromotion($args, $auth));
    }

    public function individual(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->promotionWorkflows->processIndividualPromotion($args, $auth));
    }
}
