<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Timer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimerController extends Controller
{
    public function store(Request $request, int $id): ?Timer
    {
        $data = $request->validate([
            'name' => 'required|between:3,100'
        ]);

        $timer =Task::mine()->findOrFail($id)
            ->timers()->save(new Timer([
                'name' => $data['name'],
                'user_id' => Auth::id(),
                'started_at' => new Carbon(),
            ]));

        return $timer->with('task')->find($timer->id);
    }

    public function running(): Timer|array
    {
        return Timer::with('tasks')->mine()->running()->first() ?? [];
    }

    public function stopRunning(): Timer
    {
        if ($timer = Timer::mine()->running()->first()) {
            $timer->update(['stopped_at' => new Carbon]);
        }

        return $timer;
    }
}