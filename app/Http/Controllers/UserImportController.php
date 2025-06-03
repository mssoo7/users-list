<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserImportController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/upload-users",
     *     tags={"Users"},
     *     summary="Import users from file",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="file",
     *                     description="CSV or JSON file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users imported successfully"
     *     )
     * )
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt,json'
            ]);

            $extension = $request->file('file')->getClientOriginalExtension();
            $path = $request->file('file')->getRealPath();

            $data = [];

            if ($extension === 'csv' || $extension === 'txt') {
                $rows = array_map('str_getcsv', file($path));
                $headers = array_map('strtolower', array_shift($rows));
                $data = array_map(function ($row) use ($headers) {
                    return array_combine($headers, $row);
                }, $rows);
            } elseif ($extension === 'json') {
                $data = json_decode(file_get_contents($path), true);
            } else {
                return response()->json(['error' => 'Unsupported file type'], 400);
            }

            if (empty($data)) {
                return response()->json(['error' => 'No valid data found in the file'], 422);
            }

            $userMap = [];

            foreach ($data as $row) {
                $user = User::firstOrCreate(
                    ['username' => trim($row['username'])],
                    [
                        'name' => trim($row['name']),
                        'password' => Hash::make($row['password']),
                        'parent_id' => null
                    ]
                );

                $userMap[Str::lower(trim($user->username))] = $user;
            }

            foreach ($data as $row) {
                $username = Str::lower(trim($row['username']));
                $parentName = isset($row['parent_name']) ? Str::lower(trim($row['parent_name'])) : null;

                if ($parentName && isset($userMap[$parentName])) {
                    $user = $userMap[$username];
                    $user->parent_id = $userMap[$parentName]->id;
                    $user->save();
                }
            }

            return response()->json(['message' => 'Users imported successfully.']);
        } catch (\Exception $e) {
            Log::error('User import failed: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error. Check logs.'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user-tree",
     *     summary="Get hierarchical user tree",
     *     tags={"Users"},
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchical user tree"
     *     )
     * )
     */
    public function tree()
    {
        $rootUsers = User::whereNull('parent_id')->with('children')->get();

        return response()->json($this->renderTree($rootUsers));
    }

    private function renderTree($users)
    {
        $result = [];

        foreach ($users as $user) {
            $entry = ['name' => $user->name];
            if ($user->children->isNotEmpty()) {
                $entry['children'] = $this->renderTree($user->children);
            }
            $result[] = $entry;
        }

        return $result;
    }

    public function showTree()
    {
        $users = User::with('children')->whereNull('parent_id')->get();

        return view('tree', ['users' => $users]);
    }
}
