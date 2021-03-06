<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;


use DB;
use App\Member;
use App\MemberPicture;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Custom\ResponseCodes;

class MemberPictureController
{		
    use ProcessesMemberRecordsTrait, HandlesImageUploadsTrait;

	private $tmpDir;			// Use the PHP default
	
	public function __construct(){
		$this->tmpDir = sys_get_temp_dir();
	}
	
    public function store(Request $request, $memberId)
    {	
		// Upload a chunk, then
		// check if all chunks are done. If so
		// save the chunk to the database

		$config = new \Flow\Config();
		$config->setTempDir($this->tmpDir);
        $flowRequest = new \Flow\Request();
		$file = new \Flow\File($config);

		if ($file->validateChunk()) {
			$file->saveChunk();
		} 
		else {
			// error, invalid chunk upload request, retry
			return response('', Response::HTTP_BAD_REQUEST);		// 400
		}
        

		// Check for completion
		if ($file->validateFile()) {
            $temp = tempnam('/tmp/flares', 'mbr');
			if ($file->save($temp)){
                $blob = file_get_contents($temp);
                unlink($temp);
            }
            else {
                return response('File Save Failed', Response::HTTP_INTERNAL_SERVER_ERROR);		// 201
            }
			
			$mimeType = $this->parseImageMimeType(strrchr($flowRequest->getFileName(), '.'));
			if (!$mimeType){
				// Not going to save if we don't know the mime type
				return response('Cannot determine image mime type from filename: ' . $flowRequest->getFileName(), Response::HTTP_UNSUPPORTED_MEDIA_TYPE); 	// 415
			}
			
			// // If we wanted to ensure a 1-to-1 mapping for Member record and MemberPicture
			// MemberPicture::updateOrCreate(['regt_num' => $memberId], [
				// 'photo_blob' => $blob,
				// 'file_size' => $file->size(),
				// 'mime_type' => $mimeType
			// ]);
			
			$mp = new MemberPicture();
			// $mp->regt_num = $memberId;
			$mp->photo_blob = $blob;
			$mp->file_name = $flowRequest->getFileName();
			$mp->file_size = $flowRequest->getTotalSize();
			$mp->mime_type = $mimeType;
			
			$member = Member::findOrFail($memberId);
			$member->pictures()->save($mp);
			
			return response('Upload OK', Response::HTTP_CREATED);		// 201
		}
		
		return response('', Response::HTTP_ACCEPTED);		// 202
    }
	
	/*
	 * Used as a query by the client-side o check if a chunk was uploaded yet
	 */
	public function chunkCheck(){	
		$config = new \Flow\Config();
		$config->setTempDir($this->tmpDir);		
		$file = new \Flow\File($config);
		
		if ($file->checkChunk()) {
			return response('', Response::HTTP_OK);				// 200
		} 
		return response('', Response::HTTP_NO_CONTENT);		// 204
	}
	
	/*
	 * Return a status code and JSON obj indicating if the image resource/s exists (without returning
	 * the actual image data)
	 */
	public function exists($memberId){
		$all = MemberPicture::where('regt_num', $memberId)->orderBy('created_at', 'desc')->get();
		if ($all->isEmpty()){
			return response()->json([
                'count' => 0, 'exists' => false], 404);
		}
		return response()
            ->json([
                'count' => $all->count(), 
                'url' => route('media::memberPicture', ['memberId' => $memberId]),
                'exists' => true,
            ]);
	}

	/*
	 * Serve the image as a resource
	 */
    public function show($memberId)
    {
        // Get the most recent image, serve it as whatever mimetype is recorded
		$mp = MemberPicture::where('regt_num', $memberId)->orderBy('created_at', 'desc')->firstOrFail();
		$blob = $mp->photo_blob;
		return response($blob)->withHeaders([
            'Content-Type' => $mp->mime_type,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function destroy(Request $request, $memberId)
    {
		// remove -- [ all | last ]
		$removeMode = $request->input('remove', 'last');
		$wasDeleted = false;
		
		if ($removeMode == 'all'){
			$all = MemberPicture::where('regt_num', $memberId)->get(); 
			if (!$all->isEmpty()){
				$all->each(function($memberPicture, $key){
					$memberPicture->delete();
				});
				$wasDeleted = true;
			}			
		}
		else {
			// Remove the latest only
			$latest = MemberPicture::where('regt_num', $memberId)->orderBy('created_at', 'desc')->first();
			if ($latest != null){
				$latest->delete();
				$wasDeleted = true;
			}			
		}
		return $wasDeleted ? response('', Response::HTTP_OK) : response('', Response::HTTP_NOT_FOUND);
    }
    
}
