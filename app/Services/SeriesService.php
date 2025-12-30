<?php

namespace App\Services;

use App\Models\Series;

class SeriesService
{ 

    /**
     * Get series by slug
     * 
     * @param int $id
     * 
     * @return App\Models\Series
     * 
     */
    public function get($slug)
    {
        $series = Series::where('slug', $slug)->first();
        $value = str_pad( $series->starting_value, $series->max_digit,'0', STR_PAD_LEFT);
        $series_format = $series->prefix.'-'.date("Y").'-'.$value ;
        if(!$series->update(['starting_value' => ($series->starting_value + 1) ])) {
            return false;
        }
        return $series_format;
    }
}