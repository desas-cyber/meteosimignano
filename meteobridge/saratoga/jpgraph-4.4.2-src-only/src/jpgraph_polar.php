<?php
/*=======================================================================
 // File:        JPGRAPH_POLAR.PHP
 // Description: Polar plot extension for JpGraph
 // Created:     2003-02-02
 // Ver:         $Id: jpgraph_polar.php 1796 2009-09-07 09:37:19Z ljp $
 //
 // Copyright (c) Asial Corporation. All rights reserved.
 //========================================================================
 */

require_once ('jpgraph_plotmark.inc.php');
require_once "jpgraph_log.php";


define('POLAR_360',1);
define('POLAR_180',2);

//
// Note. Don't attempt to make sense of this code.
// In order not to have to be able to inherit the scaling code
// from the main graph package we have had to make some "tricks" since
// the original scaling and axis was not designed to do what is
// required here.
// There were two option. 1: Re-implement everything and get a clean design
// and 2: do some "small" trickery and be able to inherit most of
// the functionlity from the main graph package.
// We choose 2: here in order to save some time.
//

//--------------------------------------------------------------------------
// class PolarPlot
//--------------------------------------------------------------------------
class PolarPlot {
    public $line_style='solid',$mark;
    public $legendcsimtarget='';
    public $legendcsimalt='';
    public $legend="";
    public $csimtargets=array(); // Array of targets for CSIM
    public $csimareas="";   // Resultant CSIM area tags
    public $csimalts=null;   // ALT:s for corresponding target
    public $scale=null;
    private $numpoints=0;
    private $iColor='navy',$iFillColor='';
    private $iLineWeight=1;
    private $coord=null;

    function __construct($aData) {
        $n = count($aData);
        if( $n & 1 ) {
            JpGraphError::RaiseL(17001);
            //('Polar plots must have an even number of data point. Each data point is a tuple (angle,radius).');
        }
        $this->numpoints = $n/2;
        $this->coord = $aData;
        $this->mark = new PlotMark();
    }

    function SetWeight($aWeight) {
        $this->iLineWeight = $aWeight;
    }

    function SetColor($aColor){
        $this->iColor = $aColor;
    }

    function SetFillColor($aColor){
        $this->iFillColor = $aColor;
    }

    function Max() {
        $m = $this->coord[1];
        $i=1;
        while( $i < $this->numpoints ) {
            $m = max($m,$this->coord[2*$i+1]);
            ++$i;
        }
        return $m;
    }
    // Set href targets for CSIM
    function SetCSIMTargets($aTargets,$aAlts=null) {
        $this->csimtargets=$aTargets;
        $this->csimalts=$aAlts;
    }

    // Get all created areas
    function GetCSIMareas() {
        return $this->csimareas;
    }

    function SetLegend($aLegend,$aCSIM="",$aCSIMAlt="") {
        $this->legend = $aLegend;
        $this->legendcsimtarget = $aCSIM;
        $this->legendcsimalt = $aCSIMAlt;
    }

    // Private methods

    function Legend($aGraph) {
        $color = $this->iColor ;
        if( $this->legend != "" ) {
            if( $this->iFillColor!='' ) {
                $color = $this->iFillColor;
                $aGraph->legend->Add($this->legend,$color,$this->mark,0,
                $this->legendcsimtarget,$this->legendcsimalt);
            }
            else {
                $aGraph->legend->Add($this->legend,$color,$this->mark,$this->line_style,
                $this->legendcsimtarget,$this->legendcsimalt);
            }
        }
    }

    function Stroke($img,$scale) {

        $i=0;
        $p=array();
        $this->csimareas='';
        while($i < $this->numpoints) {
            list($x1,$y1) = $scale->PTranslate($this->coord[2*$i],$this->coord[2*$i+1]);
            $p[2*$i] = $x1;
            $p[2*$i+1] = $y1;

            if( isset($this->csimtargets[$i]) ) {
                $this->mark->SetCSIMTarget($this->csimtargets[$i]);
                $this->mark->SetCSIMAlt($this->csimalts[$i]);
                $this->mark->SetCSIMAltVal($this->coord[2*$i], $this->coord[2*$i+1]);
                $this->mark->Stroke($img,$x1,$y1);
                $this->csimareas .= $this->mark->GetCSIMAreas();
            }
            else {
            	$this->mark->Stroke($img,$x1,$y1);
            }

            ++$i;
        }

        if( $this->iFillColor != '' ) {
            $img->SetColor($this->iFillColor);
            $img->FilledPolygon($p);
        }
        $img->SetLineWeight($this->iLineWeight);
        $img->SetColor($this->iColor);
        $img->Polygon($p,$this->iFillColor!='');
    }
}

//--------------------------------------------------------------------------
// class PolarAxis
//--------------------------------------------------------------------------
class PolarAxis extends Axis {
    private $angle_step=15,$angle_color='lightgray',$angle_label_color='black';
    private $angle_fontfam=FF_FONT1,$angle_fontstyle=FS_NORMAL,$angle_fontsize=10;
    private $angle_fontcolor = 'navy';
    private $gridminor_color='lightgray',$gridmajor_color='lightgray';
    private $show_minor_grid = false, $show_major_grid = true ;
    private $show_angle_mark=true, $show_angle_grid=true, $show_angle_label=true;
    private $angle_tick_len=3, $angle_tick_len2=3, $angle_tick_color='black';
    private $show_angle_tick=true;
    private $radius_tick_color='black';

    function __construct($img,$aScale) {
        parent::__construct($img,$aScale);
    }

    function ShowAngleDegreeMark($aFlg=true) {
        $this->show_angle_mark = $aFlg;
    }

    function SetAngleStep($aStep) {
        $this->angle_step=$aStep;
    }

    function HideTicks($aFlg=true,$aAngleFlg=true) {
        parent::HideTicks($aFlg,$aFlg);
        $this->show_angle_tick = !$aAngleFlg;
    }

    function ShowAngleLabel($aFlg=true) {
        $this->show_angle_label = $aFlg;
    }

    function ShowGrid($aMajor=true,$aMinor=false,$aAngle=true) {
        $this->show_minor_grid = $aMinor;
        $this->show_major_grid = $aMajor;
        $this->show_angle_grid = $aAngle ;
    }

    function SetAngleFont($aFontFam,$aFontStyle=FS_NORMAL,$aFontSize=10) {
        $this->angle_fontfam = $aFontFam;
        $this->angle_fontstyle = $aFontStyle;
        $this->angle_fontsize = $aFontSize;
    }

    function SetColor($aColor,$aRadColor='',$aAngleColor='') {
        if( $aAngleColor == '' )
        $aAngleColor=$aColor;
        parent::SetColor($aColor,$aRadColor);
        $this->angle_fontcolor = $aAngleColor;
    }

    function SetGridColor($aMajorColor,$aMinorColor='',$aAngleColor='') {
        if( $aMinorColor == '' )
        $aMinorColor = $aMajorColor;
        if( $aAngleColor == '' )
        $aAngleColor = $aMajorColor;

        $this->gridminor_color = $aMinorColor;
        $this->gridmajor_color = $aMajorColor;
        $this->angle_color = $aAngleColor;
    }

    function SetTickColors($aRadColor,$aAngleColor='') {
        $this->radius_tick_color = $aRadColor;
        $this->angle_tick_color = $aAngleColor;
    }

    // Private methods
    function StrokeGrid($pos) {
        $x = round($this->img->left_margin + $this->img->plotwidth/2);
        $this->scale->ticks->Stroke($this->img,$this->scale,$pos);

        // Stroke the minor arcs
        $pmin = array();
        $p = $this->scale->ticks->ticks_pos;
        $n = count($p);
        $i = 0;
        $this->img->SetColor($this->gridminor_color);
        while( $i < $n ) {
            $r = $p[$i]-$x+1;
            $pmin[]=$r;
            if( $this->show_minor_grid ) {
                $this->img->Circle($x,$pos,$r);
            }
            $i++;
        }

        $limit = max($this->img->plotwidth,$this->img->plotheight)*1.4 ;
        while( $r < $limit ) {
            $off = $r;
            $i=1;
            $r = $off + round($p[$i]-$x+1);
            while( $r < $limit && $i < $n ) {
                $r = $off+$p[$i]-$x;
                $pmin[]=$r;
                if( $this->show_minor_grid ) {
                    $this->img->Circle($x,$pos,$r);
                }
                $i++;
            }
        }

        // Stroke the major arcs
        if( $this->show_major_grid ) {
            // First determine how many minor step on
            // every major step. We have recorded the minor radius
            // in pmin and use these values. This is done in order
            // to avoid rounding errors if we were to recalculate the
            // different major radius.
            $pmaj = $this->scale->ticks->maj_ticks_pos;
            $p = $this->scale->ticks->ticks_pos;
            if( $this->scale->name == 'lin' ) {
                $step=round(($pmaj[1] - $pmaj[0])/($p[1] - $p[0]));
            }
            else {
                $step=9;
            }
            $n = round(count($pmin)/$step);
            $i = 0;
            $this->img->SetColor($this->gridmajor_color);
            $limit = max($this->img->plotwidth,$this->img->plotheight)*1.4 ;
            $off = $r;
            $i=0;
            $r = $pmin[$i*$step];
            while( $r < $limit && $i < $n ) {
                $r = $pmin[$i*$step];
                $this->img->Circle($x,$pos,$r);
                $i++;
            }
        }

        // Draw angles
        if( $this->show_angle_grid ) {
            $this->img->SetColor($this->angle_color);
            $d = max($this->img->plotheight,$this->img->plotwidth)*1.4 ;
            $a = 0;
            $p = $this->scale->ticks->ticks_pos;
            $start_radius = $p[1]-$x;
            while( $a < 360 ) {
                if( $a == 90 || $a == 270 ) {
                    // Make sure there are no rounding problem with
                    // exactly vertical lines
                    $this->img->Line($x+$start_radius*cos($a/180*M_PI)+1,
                                     $pos-$start_radius*sin($a/180*M_PI),
                                     $x+$start_radius*cos($a/180*M_PI)+1,
                                     $pos-$d*sin($a/180*M_PI));

                }
                else {
                    $this->img->Line($x+$start_radius*cos($a/180*M_PI)+1,
                                     $pos-$start_radius*sin($a/180*M_PI),
                                     $x+$d*cos($a/180*M_PI),
                                     $pos-$d*sin($a/180*M_PI));
                }
                $a += $this->angle_step;
            }
        }
    }

    function StrokeAngleLabels($pos,$type) {

        if( !$this->show_angle_label )
            return;

        $x0 = round($this->img->left_margin+$this->img->plotwidth/2)+1;

        $d = max($this->img->plotwidth,$this->img->plotheight)*1.42;
        $a = $this->angle_step;
        $t = new Text();
        $t->SetColor($this->angle_fontcolor);
        $t->SetFont($this->angle_fontfam,$this->angle_fontstyle,$this->angle_fontsize);
        $xright = $this->img->width - $this->img->right_margin;
        $ytop = $this->img->top_margin;
        $xleft = $this->img->left_margin;
        $ybottom = $this->img->height - $this->img->bottom_margin;
        $ha = 'left';
        $va = 'center';
        $w = $this->img->plotwidth/2;
        $h = $this->img->plotheight/2;
        $xt = $x0; $yt = $pos;
        $margin=5;

        $tl  = $this->angle_tick_len ; // Outer len
        $tl2 = $this->angle_tick_len2 ; // Interior len

        $this->img->SetColor($this->angle_tick_color);
        $rot90 = $this->img->a == 90 ;

        if( $type == POLAR_360 ) {

            // Corner angles of the four corners
            $ca1 = atan($h/$w)/M_PI*180;
            $ca2 = 180-$ca1;
            $ca3 = $ca1+180;
            $ca4 = 360-$ca1;
            $end = 360;

            while( $a < $end ) {
                $ca = cos($a/180*M_PI);
                $sa = sin($a/180*M_PI);
                $x = $d*$ca;
                $y = $d*$sa;
                $xt=1000;$yt=1000;
                if( $a <= $ca1 || $a >= $ca4 ) {
                    $yt = $pos - $w * $y/$x;
                    $xt = $xright + $margin;
                    if( $rot90 ) {
                        $ha = 'center';
                        $va = 'top';
                    }
                    else {
                        $ha = 'left';
                        $va = 'center';
                    }
                    $x1=$xright-$tl2; $x2=$xright+$tl;
                    $y1=$y2=$yt;
                }
                elseif( $a > $ca1 && $a < $ca2 ) {
                    $xt = $x0 + $h * $x/$y;
                    $yt = $ytop - $margin;
                    if( $rot90 ) {
                        $ha = 'left';
                        $va = 'center';
                    }
                    else {
                        $ha = 'center';
                        $va = 'bottom';
                    }
                    $y1=$ytop+$tl2;$y2=$ytop-$tl;
                    $x1=$x2=$xt;
                }
                elseif( $a >= $ca2 && $a <= $ca3 ) {
                    $yt = $pos + $w * $y/$x;
                    $xt = $xleft - $margin;
                    if( $rot90 ) {
                        $ha = 'center';
                        $va = 'bottom';
                    }
                    else {
                        $ha = 'right';
                        $va = 'center';
                    }
                    $x1=$xleft+$tl2;$x2=$xleft-$tl;
                    $y1=$y2=$yt;
                }
                else {
                    $xt = $x0 - $h * $x/$y;
                    $yt = $ybottom + $margin;
                    if( $rot90 ) {
                        $ha = 'right';
                        $va = 'center';
                    }
                    else {
                        $ha = 'center';
                        $va = 'top';
                    }
                    $y1=$ybottom-$tl2;$y2=$ybottom+$tl;
                    $x1=$x2=$xt;
           