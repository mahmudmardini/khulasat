<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // الصلاحيات في السياسات لا في المتحكّمات — المواصفة §10. والوراثة هنا
    // تجعل `$this->authorize()` متاحاً لكلّ متحكّم بلا تكرار.
    use AuthorizesRequests;
}
