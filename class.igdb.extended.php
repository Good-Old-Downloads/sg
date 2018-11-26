<?php
/*
    SceneGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/
namespace GoodOldDownloads;

class IGDB extends \Messerli90\IGDB\IGDB {
    /**
     * Returns array of objects of all genres
     * https://igdb.github.io/api/endpoints/genre/
     *
     * @return \StdClass
     * @throws \Exception
     */
    public function getGenres($fields = array('id', 'name', 'slug', 'url'))
    {
        $apiUrl = $this->getEndpoint('genres');
        $apiData = $this->apiGet($apiUrl, array('limit' => 50));

        $genreList = array();
        foreach ($this->decodeMultiple($apiData) as $key => $value) {
            array_push($genreList, $value->id);
        }

        $params = array(
            'fields' => implode(',', $fields)
        );

        $genres = $this->apiGet($apiUrl.join($genreList, ','), $params);
        return $this->decodeMultiple($genres);
    }

    /*
        Copied internally used methods from Messerli90\IGDB\IGDB
        Don't change unless Composer package is updated
    */

    /*
     *  Internally used Methods, set visibility to public to enable more flexibility
     */
    /**
     * @param $name
     * @return mixed
     */
    private function getEndpoint($name)
    {
        return rtrim($this->baseUrl, '/').'/'.self::VALID_RESOURCES[$name].'/';
    }

    /**
     * Decode the response from IGDB, extract the single resource object.
     * (Don't use this to decode the response containing list of objects)
     *
     * @param  string $apiData the api response from IGDB
     * @throws \Exception
     * @return \StdClass  an IGDB resource object
     */
    private function decodeSingle(&$apiData)
    {
        $resObj = json_decode($apiData);

        if (isset($resObj->status)) {
            $msg = "Error " . $resObj->status . " " . $resObj->message;
            throw new \Exception($msg);
        }

        if (!is_array($resObj) || count($resObj) == 0) {
            return false;
        }

        return $resObj[0];
    }

    /**
     * Decode the response from IGDB, extract the multiple resource object.
     *
     * @param  string $apiData the api response from IGDB
     * @throws \Exception
     * @return \StdClass  an IGDB resource object
     */
    private function decodeMultiple(&$apiData)
    {
        $resObj = json_decode($apiData);

        if (isset($resObj->status)) {
            $msg = "Error " . $resObj->status . " " . $resObj->message;
            throw new \Exception($msg);
        } else {
            //$itemsArray = $resObj->items;
            if (!is_array($resObj)) {
                return false;
            } else {
                return $resObj;
            }
        }
    }

    /**
     * Using CURL to issue a GET request
     *
     * @param $url
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    private function apiGet($url, $params)
    {
        $url = $url . (strpos($url, '?') === false ? '?' : '') . http_build_query($params);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'user-key' => $this->igdbKey,
                    'Accept' => 'application/json'
                ]
            ]);
        } catch (RequestException $exception) {
            if ($response = $exception->getResponse()) {
                throw new \Exception($exception);
            }
            throw new \Exception($exception);
        } catch (Exception $exception) {
            throw new \Exception($exception);
        }

        return $response->getBody();
    }
}