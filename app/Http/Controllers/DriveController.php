<?php

namespace App\Http\Controllers;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Response;
use Revolution\Google\Sheets\Sheets;
use Google_Service_Drive_DriveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\JsonResponse;

class DriveController extends Controller
{
    public const SHEET_ID = '1w83wXDxm9vfQ4O0-AAiY-PDkhbddbnfaEhN1FjzXSng';
    public const SPREADSHEET_RANGE = 'Lapas1';

    private $drive;

    private $sheet;

    public function __construct(Google_Client $client)
    {
        dd($client);
        $this->middleware(function ($request, $next) use ($client) {
            $client->refreshToken(Auth::user()->refresh_token);

            $this->drive = new Google_Service_Drive($client);
            $this->sheet = new Google_Service_Sheets($client);

            return $next($request);
        });
    }

    public function getDrive(): JsonResponse
    {
        $result = $this->sheet->spreadsheets_values->get(self::SHEET_ID, self::SPREADSHEET_RANGE);
        $rows = $result->getValues();
        array_shift($rows);

        return Response::json($rows);
    }

    public function ListFolders($id){

        $query = "mimeType='application/vnd.google-apps.folder' and '".$id."' in parents and trashed=false";

        $optParams = [
            'fields' => 'files(id, name)',
            'q' => $query
        ];

        $results = $this->drive->files->listFiles($optParams);

        if (count($results->getFiles()) == 0) {
            print "No files found.\n";
        } else {
            print "Files:\n";
            foreach ($results->getFiles() as $file) {
                dump($file->getName(), $file->getID());
            }
        }
    }

    function uploadFile(Request $request){
        if($request->isMethod('GET')){
            return view('upload');
        }else{
            $this->createFile($request->file('file'));
        }
    }

    function createStorageFile($storage_path){
        $this->createFile($storage_path);
    }

    function createFile($file, $parent_id = null){
        $name = gettype($file) === 'object' ? $file->getClientOriginalName() : $file;
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'parent' => $parent_id ? $parent_id : 'root'
        ]);

        $content = gettype($file) === 'object' ?  File::get($file) : Storage::get($file);
        $mimeType = gettype($file) === 'object' ? File::mimeType($file) : Storage::mimeType($file);

        $file = $this->drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        dd($file->id);
    }

    function deleteFileOrFolder($id){
        try {
            $this->drive->files->delete($id);
        } catch (Exception $e) {
            return false;
        }
    }


    function createFolder($folder_name){
        $folder_meta = new Google_Service_Drive_DriveFile(array(
            'name' => $folder_name,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $this->drive->files->create($folder_meta, array(
            'fields' => 'id'));
        return $folder->id;
    }
}
