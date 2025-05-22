<?php

namespace App\Http\Controllers;

use App\Models\MyClient;
use App\Services\RedisService;
use App\Services\S3Service;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    protected $redisService;
    protected $s3Service;

    public function __construct(RedisService $redisService, S3Service $s3Service)
    {
        $this->redisService = $redisService;
        $this->s3Service = $s3Service;
    }

    public function index()
    {
        $clients = MyClient::all();
        return response()->json($clients);
    }

    public function show($slug)
    {
        // check if cached
        $cachedClient = $this->redisService->getClientCache($slug);

        if ($cachedClient) {
            return response()->json(json_decode($cachedClient));
        }

        // if not cached, fetch from database
        $client = MyClient::where('slug', $slug)->firstOrFail();
        $this->redisService->setClientCache($slug, $client->toArray()); // set cache to redis
        return response()->json($client);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100|unique:my_clients',
            'client_logo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'is_project' => 'required|in:0,1',
            'self_capture' => 'required|in:0,1',
        ]);

        $clientLogoUrl = $this->s3Service->uploadLogo($request);

        $client = MyClient::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'client_logo' => $clientLogoUrl,
            'client_prefix' => $request->client_prefix,
            'is_project' => $request->is_project,
            'self_capture' => $request->self_capture,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
        ]);

        $this->redisService->setClientCache($client->slug, $client->toArray());

        return response()->json($client, 201);
    }

    public function update(Request $request, $slug)
    {
        $client = MyClient::where('slug', $slug)->firstOrFail();

        // validate input
        $request->validate([
            'name' => 'required|string|max:250',
            'client_logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_project' => 'required|in:0,1',
            'self_capture' => 'required|in:0,1',
        ]);

        if ($request->hasFile('client_logo')) {
            $client->client_logo = $this->s3Service->uploadLogo($request);
        }

        $client->update($request->all());

        // delete cache & set new
        $this->redisService->deleteClientCache($client->slug);
        $this->redisService->setClientCache($client->slug, $client->toArray());

        return response()->json($client);
    }

    public function destroy($slug)
    {
        $client = MyClient::where('slug', $slug)->firstOrFail();
        $client->delete();

        // delete cache redis
        $this->redisService->deleteClientCache($client->slug);

        return response()->json(['message' => 'Client deleted successfully']);
    }
}
