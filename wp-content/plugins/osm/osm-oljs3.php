<?php
/*  (c) Copyright 2015  Michael Kang (wp-osm-plugin.HanBlog.Net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Osm_OLJS3
{
  function addTileLayer($a_LayerName, $a_Type, $a_OverviewMapZoom, $a_MapControl, $a_ExtType, $a_ExtName, $a_ExtAddress, $a_ExtInit, $a_theme){
    Osm::traceText(DEBUG_INFO, "addTileLayer V3(".$a_LayerName.",".$a_Type.",".$a_OverviewMapZoom.")");
    $TileLayer = '';
    if ($a_Type == "osm"){
      $TileLayer .= '
      var raster = new ol.layer.Tile({
        source: new ol.source.OSM({})
      });';
    }
    else if ($a_Type == "stamen_toner"){
      $TileLayer .= '
      var raster = new ol.layer.Tile({
        source: new ol.source.Stamen({
            attributions: [
              new ol.Attribution({
                html: "MAP tiles by <a href=\"http://stamen.com/\">Stamen Design</a>, " +
                "under <a href=\"http://creativecommons.org/licenses/by/3.0/\">CC BY" +
                " 3.0</a>."
              }),
              ol.source.OSM.ATTRIBUTION
            ],
            layer: "toner"
          })
        });
      ';
      }
      else if ($a_Type == "stamen_watercolor"){
        $TileLayer .= '
          var raster = new ol.layer.Tile({
            source: new ol.source.Stamen({layer: "watercolor"})
          });';
      }
      else if ($a_Type == "stamen_terrain-labels"){
        $TileLayer .= '
        var raster = new ol.layer.Tile({
          source: new ol.source.Stamen({layer: "terrain-labels"})
        });';
      }
      else if ($a_Type == "openseamap"){
        $TileLayer .= '
          var raster = new ol.layer.Tile({
            source: new ol.source.OSM()
          });
          var Layer2 = new ol.layer.Tile({ 
            source: new ol.source.OSM({
              attributions: [
              new ol.Attribution({
                html: "and &copy; " +
                "<a href=\"http://www.openseamap.org/\">OpenSeaMap</a>"
              }),
              ol.source.OSM.ATTRIBUTION
              ],
              crossOrigin: null,
              url: "http://tiles.openseamap.org/seamark/{z}/{x}/{y}.png"
            })
          });';
      }
      else {// unknwon => OSM map
        $TileLayer .= '
        var raster = new ol.layer.Tile({
          source: new ol.source.OSM()
        });';
      }
      return $TileLayer;
  }

  function addVectorLayer($a_LayerName, $a_FileName, $a_Colour, $a_Type)
  {
    Osm::traceText(DEBUG_INFO, "addVectorLayer V3(".$a_LayerName.",".$a_Type.",".$a_OverviewMapZoom.")");
    $VectorLayer = '';
    $VectorLayer .= '
    var style = {
      "Point": [new ol.style.Style({
        image: new ol.style.Circle({
          fill: new ol.style.Fill({
            color: "rgba(255,255,0,0.4)"
          }),
          radius: 5,
          stroke: new ol.style.Stroke({
            color: "#ff0",
            width: 1
          })
        })
      })],
      "LineString": [new ol.style.Style({
        stroke: new ol.style.Stroke({
          color: "#f00",
          width: 3
        })
      })],
      "MultiLineString": [new ol.style.Style({
        stroke: new ol.style.Stroke({
          color: "#0f0",
          width: 3
        })
      })]
    };';
    if ($a_Type == 'GPX'){
      $VectorLayer .= '
      var vector = new ol.layer.Vector({
        source: new ol.source.GPX({
          projection: "EPSG:3857",
          url:"'.$a_FileName.'"
        })
 /**    style: function(feature, resolution) {return style[feature.getGeometry().getType()];} */
      });';
    }
    if ($a_Type == 'kml'){
      echo "KML Type";
      $VectorLayer .= '
      var vector_kml = new ol.layer.Vector({
        source: new ol.source.KML({
          projection: "EPSG:3857",
          url:"'.$a_FileName.'"
        })
 /**    style: function(feature, resolution) {return style[feature.getGeometry().getType()];}*/
      });';
    }
  return $VectorLayer;
  }

}
?>
