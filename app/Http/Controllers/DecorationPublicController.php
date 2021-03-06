<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use App\Decoration;
use App\Http\Controllers\Controller;
use App\Http\Custom\ResponseCodes;

class DecorationPublicController extends Controller
{
    /**
     * The public list shows all decorations, grouped into tiers.
     * Also shows badge image
     */
    public function index()
    {
        $decorationTiers = $this->getDecorationTiers();
        $decorations = Decoration::ordered()->with('related')->whereNull('parent_id')->get();
        
        // Assign the image to each decoration
        foreach ($decorations as &$dec){
            $dec['badgeUrl'] = route('media::decorationBadge', ['decorationId' => $dec->dec_id]);
            if (count($dec->related)){
                foreach ($dec->related as &$related){
                    $related['badgeUrl'] = route('media::decorationBadge', ['decorationId' => $related->dec_id]);
                }
            }
        }
        
        // Mash together the decorationsTiers and the decorations
        foreach ($decorationTiers as &$tier) {
            $tierDecorations = $decorations->filter(function ($dec) use ($tier) {
                return $dec->tier == $tier->tier;
            });
            $tier->decorations = $tierDecorations;
        }
        
        // dd($decorationTiers);
        
        return view('public.decoration-list', [
            'decorationTiers' => $decorationTiers,
        ]);
        
    }
    
    public function show(Request $request, $shortcode)
    {
        $decoration = Decoration::where('shortcode', $shortcode)
            ->select('*')
            ->addSelect(DB::raw('COALESCE(precedence, 0) as adjusted_precedence'))
            ->firstOrFail();
            
        $prevDecoration = Decoration::where('tier', $decoration->tier)
            ->where('precedence', '>', $decoration->adjusted_precedence)
            ->orderBy('precedence', 'asc')
            ->first();
        $nextDecoration = Decoration::where('tier', $decoration->tier)
            ->where('precedence', '<', $decoration->adjusted_precedence)
            ->orderBy('precedence', 'desc')
            ->first();
            
        // dd($prevDecoration);
            
        return view('public.decoration', [
            'dec' => $decoration,
            'decBadgeUrl' => route('media::decorationBadge', ['decorationId' => $decoration->dec_id]),
            'prevDec' => $prevDecoration,
            'nextDec' => $nextDecoration,
        ]);
    }
    
    private function getDecorationTiers()
    {
        $tierData = DB::table('ref_misc')->where('name', 'like', 'DECORATIONS_TIER_%')->get();
        $tierListIndex = $tierData->search(function($item, $key) {
            return $item->name == 'DECORATIONS_TIER_LIST';
        });
        $tiers = [];
        if ($tierListIndex !== false){
            $tierListValues = explode(',', $tierData[$tierListIndex]->value);
            $tierListKeyNames = array_map(function($keyValue) {
                return "DECORATIONS_TIER_$keyValue";
            }, $tierListValues);
            
            $tierListReference = array_combine($tierListKeyNames, $tierListValues);
            foreach ($tierData as $tierDataItem){
                if (in_array($tierDataItem->name, array_keys($tierListReference))){
                    $tiers[] = ( object ) [
                        'tier' => $tierListReference[$tierDataItem->name],
                        'name' => $tierDataItem->value
                    ];
                }
            }
        }
        return $tiers;
    }
}
