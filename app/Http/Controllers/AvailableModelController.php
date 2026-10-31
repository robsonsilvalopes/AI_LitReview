<?php

namespace App\Http\Controllers;

use App\Models\AvailableModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvailableModelController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $models = AvailableModel::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['data' => $models]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'model_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'model_json' => 'required|array',
            'price_input' => 'nullable|numeric',
            'price_output' => 'nullable|numeric',
            'type' => 'required|in:local,openrouter',
        ]);

        $userId = Auth::id();

        $model = AvailableModel::updateOrCreate(
            [
                'user_id' => $userId,
                'model_id' => $validated['model_id'],
                'type' => $validated['type'],
            ],
            [
                'name' => $validated['name'],
                'model_json' => $validated['model_json'],
                'price_input' => $validated['price_input'],
                'price_output' => $validated['price_output'],
            ]
        );

        return response()->json([
            'success' => true,
            'model' => $model,
        ]);
    }

    public function show(AvailableModel $availableModel)
    {
        if ($availableModel->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $availableModel
        ]);
    }

    public function destroy(AvailableModel $availableModel)
    {
        if ($availableModel->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $availableModel->delete();

        return response()->json(['success' => true]);
    }
}
