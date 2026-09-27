<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    /** Mọi user đã đăng nhập đều có thể xem danh sách budget của mình */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Mọi user đã đăng nhập đều có thể tạo budget */
    public function create(User $user): bool
    {
        return true;
    }

    /** Chỉ chủ sở hữu mới được sửa */
    public function update(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }

    /** Chỉ chủ sở hữu mới được xóa */
    public function delete(User $user, Budget $budget): bool
    {
        return $budget->user_id === $user->id;
    }
}
