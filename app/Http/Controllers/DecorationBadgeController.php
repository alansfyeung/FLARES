<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use DB;
use App\Decoration;
use App\Member;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Custom\ResponseCodes;


class DecorationBadgeController
{
    use HandlesImageUploadsTrait;

	private $tmpDir;			// Use the PHP default
	
	public function __construct(){
		$this->tmpDir = sys_get_temp_dir();
	}

    public function store(Request $request, $decorationId)
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
            $temp = tempnam('/tmp/flares', 'dec');
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
            
            // TODO: Shrink this image
            // Shrink this image

			$dec = Decoration::find($decorationId);
			$dec->badge_blob = $blob;
			$dec->badge_mime_type = $mimeType;
			
			$dec->badge_w = 90;         // hardcode for now until image shrink
			$dec->badge_h = 30;         // hardcode for now until image shrink
            
            $dec->badge_uri = $dec->shortcode . strrchr($flowRequest->getFileName(), '.');
            
			$dec->save();
            
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
	public function exists($decorationId){
        $dec = Decoration::findOrFail($decorationId);
        // $imageExists = !($dec->badge_blob === null && $dec->badge_uri === null);
        $imageExists = !($dec->badge_blob === null);
        $response = [ 'exists' => $imageExists ];
        if ($imageExists){
            $response['url'] = route('media::decorationBadge', ['decorationId' => $decorationId]);
        }
        return response()->json($response);
	}

	/*
	 * Serve the image as a resource
	 */
    public function show($decorationId)
    {
        // Get the most recent image, serve it as whatever mimetype is recorded
		$dec = Decoration::findOrFail($decorationId);
        if ($dec->badge_blob !== null){
            return response($dec->badge_blob)->withHeaders([
                'Content-Type' => $dec->badge_mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } // elseif ($dec->badge_uri !== null) {
            // $url = $dec->badge_uri;
            // // Assume it's a local image if not fully qualified url
            // if (!str_contains($dec->badge_uri, '://')){   
                // $url = secure_asset($url);
            // }
            // return redirect($url);
        // }
        return response('', 404);
    }

    public function destroy(Request $request, $decorationId)
    {
        try {
            
            $dec = Decoration::findOrFail($decorationId);
            $dec->badge_blob = null;
            $dec->badge_mime_type = null;
            $dec->badge_uri = null;
            $dec->badge_w = null;
            $dec->badge_h = null;
            $dec->save();            
            return response('', Response::HTTP_NO_CONTENT);
            
        } catch (\Exception $ex) {
            
            // Not perfect... maybe filter based on 404 or otherwise
            return response('', Response::HTTP_NOT_FOUND);
            
        }
    }
    
}
