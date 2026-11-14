<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreOurTeamMemberRequest;
use App\Http\Requests\Organisation\UpdateOurTeamMemberRequest;
use App\Http\Resources\OurTeamMemberResource;
use App\Services\Organisation\OurTeamMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OurTeamMemberController extends Controller
{
    public function list(Request $request, OurTeamMemberService $members): JsonResponse
    {
        try {
            $filters = [
                'site_id' => $request->filled('site_id') ? $request->integer('site_id') : null,
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $members->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.our_team_member_list'),
                OurTeamMemberResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function create(
        StoreOurTeamMemberRequest $request,
        OurTeamMemberService $members,
    ): JsonResponse {
        try {
            $response = $members->create($request->user(), $request->validated());

            return $this->success(
                __('messages.our_team_member_created'),
                [
                    'member' => OurTeamMemberResource::make($response['member']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function update(
        UpdateOurTeamMemberRequest $request,
        OurTeamMemberService $members,
        int $member_id,
    ): JsonResponse {
        try {
            $response = $members->update($request->user(), $member_id, $request->validated());

            return $this->success(
                __('messages.our_team_member_updated'),
                [
                    'member' => OurTeamMemberResource::make($response['member']),
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    public function delete(
        Request $request,
        OurTeamMemberService $members,
        int $member_id,
    ): JsonResponse {
        try {
            $members->delete($request->user(), $member_id);

            return $this->success(
                __('messages.our_team_member_deleted'),
                [
                    'deleted' => true,
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
