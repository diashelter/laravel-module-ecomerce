<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest;
use App\Modules\Identity\Http\Resources\StaffMemberResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\UseCases\CreateStaffMemberUseCase;
use App\Modules\Identity\UseCases\DeleteStaffMemberUseCase;
use App\Modules\Identity\UseCases\ListStaffMembersUseCase;
use App\Modules\Identity\UseCases\UpdateStaffMemberUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Staff management, `admin` role only (the route group carries the `admin` middleware).
 */
class StaffMemberController extends Controller
{
    public function index(ListStaffMembersUseCase $listStaffMembers): AnonymousResourceCollection
    {
        return StaffMemberResource::collection($listStaffMembers->execute(15));
    }

    public function store(StoreStaffMemberRequest $request, CreateStaffMemberUseCase $createStaffMember): JsonResponse
    {
        return StaffMemberResource::make($createStaffMember->execute($request->toDto()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): StaffMemberResource
    {
        return StaffMemberResource::make($user);
    }

    public function update(UpdateStaffMemberRequest $request, User $user, UpdateStaffMemberUseCase $updateStaffMember): StaffMemberResource
    {
        return StaffMemberResource::make($updateStaffMember->execute($request->user('staff'), $user, $request->toDto()));
    }

    public function destroy(Request $request, User $user, DeleteStaffMemberUseCase $deleteStaffMember): Response
    {
        $deleteStaffMember->execute($request->user('staff'), $user);

        return response()->noContent();
    }
}
