<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Identity\Core\UseCases\CreateStaffMemberUseCase;
use Modules\Identity\Core\UseCases\DeleteStaffMemberUseCase;
use Modules\Identity\Core\UseCases\ListStaffMembersUseCase;
use Modules\Identity\Core\UseCases\UpdateStaffMemberUseCase;
use Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest;
use Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest;
use Modules\Identity\Http\Resources\StaffMemberResource;
use Modules\Identity\Persistence\Repositories\UserRepository;
use Modules\Shared\Http\Controllers\Controller;

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

    public function show(string $user, UserRepository $users): StaffMemberResource
    {
        return StaffMemberResource::make($users->findOrFail($user));
    }

    public function update(UpdateStaffMemberRequest $request, string $user, UpdateStaffMemberUseCase $updateStaffMember): StaffMemberResource
    {
        return StaffMemberResource::make($updateStaffMember->execute($request->user('staff'), $user, $request->toDto()));
    }

    public function destroy(Request $request, string $user, DeleteStaffMemberUseCase $deleteStaffMember): Response
    {
        $deleteStaffMember->execute($request->user('staff'), $user);

        return response()->noContent();
    }
}
