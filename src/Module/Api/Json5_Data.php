<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2023
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Ampache\Module\Api;

use Ampache\Repository\LicenseRepositoryInterface;
use Ampache\Repository\Model\Album;
use Ampache\Repository\Model\Bookmark;
use Ampache\Repository\Model\Label;
use Ampache\Repository\Model\Live_Stream;
use Ampache\Module\Util\ObjectTypeToClassNameMapper;
use Ampache\Config\AmpConfig;
use Ampache\Repository\Model\Art;
use Ampache\Repository\Model\Artist;
use Ampache\Repository\Model\Catalog;
use Ampache\Module\System\Core;
use Ampache\Repository\Model\Democratic;
use Ampache\Repository\Model\Playlist;
use Ampache\Repository\Model\Podcast;
use Ampache\Repository\Model\Podcast_Episode;
use Ampache\Repository\Model\Preference;
use Ampache\Repository\Model\Rating;
use Ampache\Repository\Model\Search;
use Ampache\Repository\Model\Share;
use Ampache\Repository\Model\Shoutbox;
use Ampache\Repository\Model\Song;
use Ampache\Module\Playback\Stream;
use Ampache\Repository\Model\Tag;
use Ampache\Repository\Model\User;
use Ampache\Repository\Model\Useractivity;
use Ampache\Repository\Model\Userflag;
use Ampache\Repository\Model\Video;
use Ampache\Repository\AlbumRepositoryInterface;
use Ampache\Repository\PodcastRepositoryInterface;
use Ampache\Repository\SongRepositoryInterface;
use Traversable;

/**
 * Json5_Data Class
 *
 * This class takes care of all of the JSON document stuff in Ampache these
 * are all static calls
 *
 */
class Json5_Data
{
    // This is added so that we don't pop any webservers
    private static ?int $limit = 5000;
    private static int $offset = 0;

    /**
     * set_offset
     *
     * This takes an int and changes the offset
     *
     * @param int $offset Change the starting position of your results. (e.g 5001 when selecting in groups of 5000)
     */
    public static function set_offset($offset): void
    {
        self::$offset = (int)$offset;
    }

    /**
     * set_limit
     *
     * This sets the limit for any ampache transactions
     *
     * @param int|string $limit Set a limit on your results
     */
    public static function set_limit($limit): bool
    {
        if (!$limit) {
            return false;
        }

        self::$limit = (strtolower((string) $limit) == "none") ? null : (int)$limit;

        return true;
    }

    /**
     * error
     *
     * This generates a JSON Error message
     * nothing fancy here...
     *
     * @param int|string $code Error code
     * @param string $string Error message
     * @param string $action Error method
     * @param string $type Error type
     */
    public static function error($code, $string, $action, $type): string
    {
        $message = array("error" => array("errorCode" => (string) $code, "errorAction" => $action, "errorType" => $type, "errorMessage" => $string));

        return json_encode($message, JSON_PRETTY_PRINT);
    }

    /**
     * success
     *
     * This generates a standard JSON Success message
     * nothing fancy here...
     *
     * @param string $string success message
     * @param array $return_data
     */
    public static function success($string, $return_data = array()): string
    {
        $message = array("success" => $string);
        foreach ($return_data as $title => $data) {
            $message[$title] = $data;
        }

        return json_encode($message, JSON_PRETTY_PRINT);
    }

    /**
     * empty
     *
     * This generates a JSON empty object
     * nothing fancy here...
     *
     * @param string $type object type
     */
    public static function empty($type): string
    {
        return json_encode(array($type => array()), JSON_PRETTY_PRINT);
    }

    /**
     * genre_array
     *
     * This returns the formatted 'genre' array for a JSON document
     * @param array $tags
     */
    private static function genre_array($tags): array
    {
        $JSON = array();

        if (!empty($tags)) {
            $atags = array();
            foreach ($tags as $tag_id => $data) {
                if (array_key_exists($data['id'], $atags)) {
                    $atags[$data['id']]['count']++;
                } else {
                    $atags[$data['id']] = array(
                        'name' => $data['name'],
                        'count' => 1
                    );
                }
            }

            foreach ($atags as $tag_id => $data) {
                $JSON[] = array(
                    "id" => (string)$tag_id,
                    "name" => $data['name']
                );
            }
        }

        return $JSON;
    }

    /**
     * indexes
     *
     * This takes an array of object_ids and return JSON based on the type of object
     *
     * @param array $objects Array of object_ids (Mixed string|int)
     * @param string $type 'artist'|'album'|'song'|'playlist'|'share'|'podcast'|'podcast_episode'|'video'|'live_stream'
     * @param User $user
     * @param bool $include (add the extra songs details if a playlist or podcast_episodes if a podcast)
     * @return string  JSON Object "artist"|"album"|"song"|"playlist"|"share"|"podcast"|"podcast_episode"|"video"|"live_stream"
     */
    public static function indexes($objects, $type, $user, $include = false)
    {
        // here is where we call the object type
        switch ($type) {
            case 'song':
                return self::songs($objects, $user);
            case 'album':
                $include_array = ($include) ? array('songs') : array();

                return self::albums($objects, $include_array, $user);
            case 'artist':
                $include_array = ($include) ? array('songs', 'albums') : array();

                return self::artists($objects, $include_array, $user);
            case 'playlist':
                return self::playlists($objects, $user, $include);
            case 'share':
                return self::shares($objects);
            case 'podcast':
                return self::podcasts($objects, $user, $include);
            case 'podcast_episode':
                return self::podcast_episodes($objects, $user);
            case 'video':
                return self::videos($objects, $user);
            case 'live_stream':
                return self::live_streams($objects);
            default:
                /* HINT: Requested object string/id/type ("album", "myusername", "some song title", 1298376) */
                return self::error('4710', sprintf(T_('Bad Request: %s'), $type), 'indexes', 'type');
        }
    }

    /**
     * live_streams
     *
     * This returns live_streams to the user, in a pretty JSON document with the information
     *
     * @param int[] $live_streams
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function live_streams($live_streams, $object = true): string
    {
        if ((count($live_streams) > self::$limit || self::$offset > 0) && self::$limit) {
            $live_streams = array_splice($live_streams, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($live_streams as $live_stream_id) {
            $live_stream = new Live_Stream($live_stream_id);
            if ($live_stream->isNew()) {
                continue;
            }
            $live_stream->format();
            $JSON[] = array(
                "id" => (string)$live_stream_id,
                "name" => $live_stream->get_fullname(),
                "url" => $live_stream->url,
                "codec" => $live_stream->codec,
                "catalog" => (string)$live_stream->catalog,
                "site_url" => $live_stream->site_url
            );
        } // end foreach
        $output = ($object) ? array("live_stream" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * licenses
     *
     * This returns licenses to the user, in a pretty JSON document with the information
     *
     * @param list<int> $licenses Licence id's assigned to songs and artists
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function licenses(array $licenses, $object = true): string
    {
        if ((count($licenses) > self::$limit || self::$offset > 0) && self::$limit) {
            $licenses = array_splice($licenses, self::$offset, self::$limit);
        }

        $licenseRepository = self::getLicenseRepository();

        $JSON = [];
        foreach ($licenses as $license_id) {
            $license = $licenseRepository->findById($license_id);

            if ($license !== null) {
                $JSON[]  = array(
                    'id' => (string) $license->getId(),
                    'name' => $license->getName(),
                    'description' => $license->getDescription(),
                    'external_link' => $license->getLinkFormatted()
                );
            }
        } // end foreach
        $output = ($object) ? array("license" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * labels
     *
     * This returns labels to the user, in a pretty JSON document with the information
     *
     * @param int[] $labels
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function labels($labels, $object = true): string
    {
        if ((count($labels) > self::$limit || self::$offset > 0) && self::$limit) {
            $labels = array_splice($labels, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($labels as $label_id) {
            $label = new Label($label_id);
            if ($label->isNew()) {
                continue;
            }
            $label->format();
            $JSON[] = array(
                "id" => (string)$label_id,
                "name" => $label->get_fullname(),
                "artists" => $label->artist_count,
                "summary" => $label->summary,
                "external_link" => $label->get_link(),
                "address" => $label->address,
                "category" => $label->category,
                "email" => $label->email,
                "website" => $label->website,
                "user" => (string)$label->user,
            );
        } // end foreach
        $output = ($object) ? array("label" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * genres
     *
     * This returns genres to the user, in a pretty JSON document with the information
     *
     * @param int[] $tags Genre id's to include
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function genres($tags, $object = true): string
    {
        if ((count($tags) > self::$limit || self::$offset > 0) && self::$limit) {
            $tags = array_splice($tags, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($tags as $tag_id) {
            $tag    = new Tag($tag_id);
            $counts = $tag->count();
            $JSON[] = array(
                "id" => (string)$tag_id,
                "name" => $tag->name,
                "albums" => (int)($counts['album'] ?? 0),
                "artists" => (int)($counts['artist'] ?? 0),
                "songs" => (int)($counts['song'] ?? 0),
                "videos" => (int)($counts['video'] ?? 0),
                "playlists" => (int)($counts['playlist'] ?? 0),
                "live_streams" => (int)($counts['live_stream'] ?? 0)
            );
        } // end foreach
        $output = ($object) ? array("genre" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * artists
     *
     * This takes an array of artists and then returns a pretty JSON document with the information
     * we want
     *
     * @param int[] $artists Artist id's to include
     * @param array $include
     * @param User $user
     * @param bool $encode
     * @param bool $object (whether to return as a named object array or regular array)
     * @return array|string JSON Object "artist"
     */
    public static function artists($artists, $include, $user, $encode = true, $object = true)
    {
        if ((count($artists) > self::$limit || self::$offset > 0) && (self::$limit && $encode)) {
            $artists = array_splice($artists, self::$offset, self::$limit);
        }

        $JSON = [];

        Rating::build_cache('artist', $artists);
        foreach ($artists as $artist_id) {
            $artist = new Artist($artist_id);
            if ($artist->isNew()) {
                continue;
            }
            $artist->format();

            $rating      = new Rating($artist_id, 'artist');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($artist_id, 'artist');

            // Build the Art URL, include session
            $art_url = AmpConfig::get('web_path') . '/image.php?object_id=' . $artist_id . '&object_type=artist';

            // Handle includes
            $albums = (in_array("albums", $include))
                ? self::albums(static::getAlbumRepository()->getAlbumByArtist($artist_id), array(), $user, false)
                : array();
            $songs = (in_array("songs", $include))
                ? self::songs(static::getSongRepository()->getByArtist($artist_id), $user, false)
                : array();

            $JSON[] = array(
                "id" => (string)$artist->id,
                "name" => $artist->get_fullname(),
                "albums" => $albums,
                "albumcount" => $artist->album_count,
                "songs" => $songs,
                "songcount" => $artist->song_count,
                "genre" => self::genre_array($artist->tags),
                "art" => $art_url,
                "flag" => (!$flag->get_flag($user->getId()) ? 0 : 1),
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => $rating->get_average_rating(),
                "mbid" => $artist->mbid,
                "summary" => $artist->summary,
                "time" => (int)$artist->time,
                "yearformed" => (int)$artist->yearformed,
                "placeformed" => $artist->placeformed
            );
        } // end foreach artists

        if ($encode) {
            $output = ($object) ? array("artist" => $JSON) : $JSON[0] ?? array();

            return json_encode($output, JSON_PRETTY_PRINT);
        }

        return $JSON;
    }

    /**
     * albums
     *
     * This echos out a standard albums JSON document, it pays attention to the limit
     *
     * @param int[] $albums Album id's to include
     * @param array|false $include
     * @param User $user
     * @param bool $encode
     * @param bool $object (whether to return as a named object array or regular array)
     * @return array|string JSON Object "album"
     */
    public static function albums($albums, $include, $user, $encode = true, $object = true)
    {
        if ((count($albums) > self::$limit || self::$offset > 0) && (self::$limit && $encode)) {
            $albums = array_splice($albums, self::$offset, self::$limit);
        }
        // original year (fall back to regular year)
        $original_year = AmpConfig::get('use_original_year');

        Rating::build_cache('album', $albums);

        $JSON = [];
        foreach ($albums as $album_id) {
            $album = new Album($album_id);
            if ($album->isNew()) {
                continue;
            }
            $album->format();

            $rating      = new Rating($album_id, 'album');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($album_id, 'album');
            $year        = ($original_year && $album->original_year)
                ? $album->original_year
                : $album->year;

            // Build the Art URL, include session
            $art_url = AmpConfig::get('web_path') . '/image.php?object_id=' . $album->id . '&object_type=album';

            $objArray = [];

            $objArray["id"]   = (string)$album->id;
            $objArray["name"] = $album->get_fullname();

            if ($album->get_artist_fullname() != "") {
                $objArray['artist'] = array(
                    "id" => (string)$album->album_artist,
                    "name" => $album->f_artist_name
                );
            }

            // Handle includes
            $songs = ($include && in_array("songs", $include))
                ? self::songs(static::getSongRepository()->getByAlbum($album->id), $user, false)
                : array();

            $objArray['time']          = (int) $album->total_duration;
            $objArray['year']          = (int) $year;
            $objArray['tracks']        = $songs;
            $objArray['songcount']     = (int) $album->song_count;
            $objArray['diskcount']     = (int) $album->disk_count;
            $objArray['type']          = $album->release_type;
            $objArray['genre']         = self::genre_array($album->tags);
            $objArray['art']           = $art_url;
            $objArray['flag']          = (!$flag->get_flag($user->getId()) ? 0 : 1);
            $objArray['preciserating'] = $user_rating;
            $objArray['rating']        = $user_rating;
            $objArray['averagerating'] = $rating->get_average_rating();
            $objArray['mbid']          = $album->mbid;

            $JSON[] = $objArray;
        } // end foreach

        if ($encode) {
            $output = ($object) ? array("album" => $JSON) : $JSON[0] ?? array();

            return json_encode($output, JSON_PRETTY_PRINT);
        }

        return $JSON;
    }

    /**
     * playlists
     *
     * This takes an array of playlist ids and then returns a nice pretty JSON document
     *
     * @param array $playlists Playlist id's to include
     * @param User $user
     * @param bool $songs
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function playlists($playlists, $user, $songs = false, $object = true): string
    {
        if ((count($playlists) > self::$limit || self::$offset > 0) && self::$limit) {
            $playlists = array_slice($playlists, self::$offset, self::$limit);
        }
        $hide_dupe_searches = (bool)Preference::get_by_user($user->getId(), 'api_hide_dupe_searches');
        $playlist_names     = array();
        $JSON               = [];

        // Foreach the playlist ids
        foreach ($playlists as $playlist_id) {
            /**
             * Strip smart_ from playlist id and compare to original
             * smartlist = 'smart_1'
             * playlist  = 1000000
             */
            if ((int)$playlist_id === 0) {
                $playlist = new Search((int) str_replace('smart_', '', (string) $playlist_id), 'song', $user);
                if ($hide_dupe_searches && $playlist->user == $user->getId() && in_array($playlist->name, $playlist_names)) {
                    continue;
                }
                $object_type    = 'search';
                $art_url        = Art::url($playlist->id, $object_type, Core::get_request('auth'));
                $playitem_total = $playlist->last_count;
            } else {
                $playlist       = new Playlist($playlist_id);
                $object_type    = 'playlist';
                $art_url        = Art::url($playlist_id, $object_type, Core::get_request('auth'));
                $playitem_total = $playlist->get_media_count('song');
                if ($hide_dupe_searches && $playlist->user == $user->getId()) {
                    $playlist_names[] = $playlist->name;
                }
            }
            $playlist_name = $playlist->get_fullname();
            $playlist_user = $playlist->username;
            $playlist_type = $playlist->type;

            if ($songs) {
                $items          = array();
                $trackcount     = 1;
                $playlisttracks = $playlist->get_items();
                foreach ($playlisttracks as $objects) {
                    $items[] = array(
                        "id" => (string)$objects['object_id'],
                        "playlisttrack" => $trackcount
                    );
                    $trackcount++;
                }
            } else {
                $items = (int)($playitem_total ?? 0);
            }
            $rating      = new Rating($playlist_id, $object_type);
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($playlist_id, $object_type);

            // Build this element
            $JSON[] = [
                "id" => (string)$playlist_id,
                "name" => $playlist_name,
                "owner" => $playlist_user,
                "items" => $items,
                "type" => $playlist_type,
                "art" => $art_url,
                "flag" => (!$flag->get_flag($user->getId()) ? 0 : 1),
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => $rating->get_average_rating()
            ];
        } // end foreach
        $output = ($object) ? array("playlist" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * shares
     *
     * This returns shares to the user, in a pretty json document with the information
     *
     * @param int[] $shares Share id's to include
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function shares($shares, $object = true): string
    {
        if ((count($shares) > self::$limit || self::$offset > 0) && self::$limit) {
            $shares = array_splice($shares, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($shares as $share_id) {
            $share                = new Share($share_id);
            $share_name           = $share->getObjectName();
            $share_user           = $share->getUserName();
            $share_allow_stream   = (int) $share->allow_stream;
            $share_allow_download = (int) $share->allow_download;
            $share_creation_date  = $share->creation_date;
            $share_lastvisit_date = $share->lastvisit_date;
            $share_object_type    = $share->object_type;
            $share_object_id      = (string)$share->object_id;
            $share_expire_days    = (int) $share->expire_days;
            $share_max_counter    = (int) $share->max_counter;
            $share_counter        = (int) $share->counter;
            $share_secret         = $share->secret;
            $share_public_url     = $share->public_url;
            $share_description    = $share->description;
            // Build this element
            $JSON[] = [
                "id" => (string)$share_id,
                "name" => $share_name,
                "owner" => $share_user,
                "allow_stream" => $share_allow_stream,
                "allow_download" => $share_allow_download,
                "creation_date" => $share_creation_date,
                "lastvisit_date" => $share_lastvisit_date,
                "object_type" => $share_object_type,
                "object_id" => $share_object_id,
                "expire_days" => $share_expire_days,
                "max_counter" => $share_max_counter,
                "counter" => $share_counter,
                "secret" => $share_secret,
                "public_url" => $share_public_url,
                "description" => $share_description
            ];
        } // end foreach
        $output = ($object) ? array("share" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * bookmarks
     *
     * This returns bookmarks to the user, in a pretty json document with the information
     *
     * @param int[] $bookmarks Bookmark id's to include
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function bookmarks($bookmarks, $object = true): string
    {
        if ((count($bookmarks) > self::$limit || self::$offset > 0) && self::$limit) {
            $bookmarks = array_splice($bookmarks, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($bookmarks as $bookmark_id) {
            $bookmark               = new Bookmark($bookmark_id);
            $bookmark_username      = $bookmark->getUserName();
            $bookmark_object_type   = $bookmark->object_type;
            $bookmark_object_id     = (string)$bookmark->object_id;
            $bookmark_position      = $bookmark->position;
            $bookmark_comment       = $bookmark->comment;
            $bookmark_creation_date = $bookmark->creation_date;
            $bookmark_update_date   = $bookmark->update_date;
            // Build this element
            $JSON[] = [
                "id" => (string)$bookmark_id,
                "owner" => $bookmark_username,
                "object_type" => $bookmark_object_type,
                "object_id" => $bookmark_object_id,
                "position" => $bookmark_position,
                "client" => $bookmark_comment,
                "creation_date" => $bookmark_creation_date,
                "update_date" => $bookmark_update_date
            ];
        } // end foreach
        $output = ($object) ? array("bookmark" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * catalogs
     *
     * This returns catalogs to the user, in a pretty json document with the information
     *
     * @param int[] $catalogs group of catalog id's
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function catalogs($catalogs, $object = true): string
    {
        if ((count($catalogs) > self::$limit || self::$offset > 0) && self::$limit) {
            $catalogs = array_splice($catalogs, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($catalogs as $catalog_id) {
            $catalog = Catalog::create_from_id($catalog_id);
            if ($catalog === null) {
                break;
            }
            $catalog->format();
            $catalog_name           = $catalog->name;
            $catalog_type           = $catalog->catalog_type;
            $catalog_gather_types   = $catalog->gather_types;
            $catalog_enabled        = (int) $catalog->enabled;
            $catalog_last_add       = $catalog->last_add;
            $catalog_last_clean     = $catalog->last_clean;
            $catalog_last_update    = $catalog->last_update;
            $catalog_path           = $catalog->f_info;
            $catalog_rename_pattern = $catalog->rename_pattern;
            $catalog_sort_pattern   = $catalog->sort_pattern;
            // Build this element
            $JSON[] = [
                "id" => (string)$catalog_id,
                "name" => $catalog_name,
                "type" => $catalog_type,
                "gather_types" => $catalog_gather_types,
                "enabled" => $catalog_enabled,
                "last_add" => $catalog_last_add,
                "last_clean" => $catalog_last_clean,
                "last_update" => $catalog_last_update,
                "path" => $catalog_path,
                "rename_pattern" => $catalog_rename_pattern,
                "sort_pattern" => $catalog_sort_pattern
            ];
        } // end foreach
        $output = ($object) ? array("catalog" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * podcasts
     *
     * This returns podcasts to the user, in a pretty json document with the information
     *
     * @param int[] $podcasts Podcast id's to include
     * @param User $user
     * @param bool $episodes include the episodes of the podcast
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function podcasts($podcasts, $user, $episodes = false, $object = true): string
    {
        if ((count($podcasts) > self::$limit || self::$offset > 0) && self::$limit) {
            $podcasts = array_splice($podcasts, self::$offset, self::$limit);
        }

        $podcastRepository = self::getPodcastRepository();

        $JSON = [];
        foreach ($podcasts as $podcast_id) {
            $podcast = $podcastRepository->findById($podcast_id);

            if ($podcast === null) {
                continue;
            }

            $rating              = new Rating($podcast_id, 'podcast');
            $user_rating         = $rating->get_user_rating($user->getId());
            $flag                = new Userflag($podcast_id, 'podcast');
            $art_url             = Art::url($podcast_id, 'podcast', Core::get_request('auth'));
            $podcast_name        = $podcast->get_fullname();
            $podcast_description = $podcast->get_description();
            $podcast_language    = scrub_out($podcast->getLanguage());
            $podcast_copyright   = scrub_out($podcast->getCopyright());
            $podcast_feed_url    = $podcast->getFeedUrl();
            $podcast_generator   = scrub_out($podcast->getGenerator());
            $podcast_website     = scrub_out($podcast->getWebsite());
            $podcast_build_date  = $podcast->getLastBuildDate()->format(DATE_ATOM);
            $podcast_sync_date   = $podcast->getLastSyncDate()->format(DATE_ATOM);
            $podcast_public_url  = $podcast->get_link();
            $podcast_episodes    = array();
            if ($episodes) {
                $results          = $podcastRepository->getEpisodes($podcast);
                $podcast_episodes = self::podcast_episodes($results, $user, false);
            }
            // Build this element
            $JSON[] = [
                "id" => (string)$podcast_id,
                "name" => $podcast_name,
                "description" => $podcast_description,
                "language" => $podcast_language,
                "copyright" => $podcast_copyright,
                "feed_url" => $podcast_feed_url,
                "generator" => $podcast_generator,
                "website" => $podcast_website,
                "build_date" => $podcast_build_date,
                "sync_date" => $podcast_sync_date,
                "public_url" => $podcast_public_url,
                "art" => $art_url,
                "flag" => (!$flag->get_flag($user->getId()) ? 0 : 1),
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => $rating->get_average_rating(),
                "podcast_episode" => $podcast_episodes
            ];
        } // end foreach
        $output = ($object) ? array("podcast" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * podcast_episodes
     *
     * This returns podcasts to the user, in a pretty json document with the information
     *
     * @param int[] $podcast_episodes Podcast_Episode id's to include
     * @param User $user
     * @param bool $encode
     * @param bool $object (whether to return as a named object array or regular array)
     * @return array|string JSON Object "podcast_episode"
     */
    public static function podcast_episodes($podcast_episodes, $user, $encode = true, $object = true)
    {
        if ((count($podcast_episodes) > self::$limit || self::$offset > 0) && (self::$limit && $encode)) {
            $podcast_episodes = array_splice($podcast_episodes, self::$offset, self::$limit);
        }
        $JSON = array();
        foreach ($podcast_episodes as $episode_id) {
            $episode = new Podcast_Episode($episode_id);
            if ($episode->isNew()) {
                continue;
            }
            $episode->format();
            $rating      = new Rating($episode_id, 'podcast_episode');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($episode_id, 'podcast_episode');
            $art_url     = Art::url($episode->podcast, 'podcast', Core::get_request('auth'));
            $JSON[]      = [
                "id" => (string)$episode_id,
                "title" => $episode->get_fullname(),
                "name" => $episode->get_fullname(),
                "description" => $episode->f_description,
                "category" => $episode->f_category,
                "author" => $episode->f_author,
                "author_full" => $episode->f_artist_full,
                "website" => $episode->f_website,
                "pubdate" => $episode->f_pubdate,
                "state" => $episode->f_state,
                "filelength" => $episode->f_time_h,
                "filesize" => $episode->f_size,
                "filename" => $episode->f_file,
                "mime" => $episode->mime,
                "time" => (int)$episode->time,
                "size" => (int)$episode->size,
                "public_url" => $episode->get_link(),
                "url" => $episode->play_url('', 'api', false, $user->getId(), $user->streamtoken),
                "catalog" => (string)$episode->catalog,
                "art" => $art_url,
                "flag" => (!$flag->get_flag($user->getId()) ? 0 : 1),
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => $rating->get_average_rating(),
                "playcount" => (int)$episode->total_count,
                "played" => (string)$episode->played
            ];
        }
        if (!$encode) {
            return $JSON;
        }
        $output = ($object) ? array("podcast_episode" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * songs
     *
     * This returns an array of songs populated from an array of song ids.
     * (Spiffy isn't it!)
     * @param int[] $songs
     * @param User $user
     * @param bool $encode
     * @param bool $object (whether to return as a named object array or regular array)
     * @return array|string JSON Object "song"
     */
    public static function songs($songs, $user, $encode = true, $object = true)
    {
        if ((count($songs) > self::$limit || self::$offset > 0) && (self::$limit && $encode)) {
            $songs = array_slice($songs, self::$offset, self::$limit);
        }

        Song::build_cache($songs);
        Stream::set_session($_REQUEST['auth'] ?? '');

        $JSON           = [];
        $playlist_track = 0;

        // Foreach the ids!
        foreach ($songs as $song_id) {
            $song = new Song($song_id);
            // If the song id is invalid/null
            if ($song->isNew()) {
                continue;
            }
            $song->format();
            $rating      = new Rating($song_id, 'song');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($song_id, 'song');
            $art_url     = Art::url($song->album, 'album', $_REQUEST['auth'] ?? '');
            $songMime    = $song->mime;
            $songBitrate = $song->bitrate;
            $play_url    = $song->play_url('', 'api', false, $user->id, $user->streamtoken);
            $license     = $song->getLicense();
            if ($license !== null) {
                $licenseLink = $license->getLinkFormatted();
            } else {
                $licenseLink = '';
            }

            $playlist_track++;

            $objArray = array(
                "id" => (string)$song->id,
                "title" => $song->get_fullname(),
                "name" => $song->get_fullname(),
                "artist" => array(
                    "id" => (string) $song->artist,
                    "name" => $song->get_artist_fullname()),
                "album" => array(
                    "id" => (string) $song->album,
                    "name" => $song->get_album_fullname()),
                'albumartist' => array(
                    "id" => (string) $song->albumartist,
                    "name" => $song->get_album_artist_fullname()
                )
            );

            $objArray['disk']                  = (int) $song->disk;
            $objArray['track']                 = (int) $song->track;
            $objArray['filename']              = $song->file;
            $objArray['genre']                 = self::genre_array($song->tags);
            $objArray['playlisttrack']         = $playlist_track;
            $objArray['time']                  = (int)$song->time;
            $objArray['year']                  = (int)$song->year;
            $objArray['bitrate']               = $songBitrate;
            $objArray['rate']                  = (int)$song->rate;
            $objArray['mode']                  = $song->mode;
            $objArray['mime']                  = $songMime;
            $objArray['url']                   = $play_url;
            $objArray['size']                  = (int)$song->size;
            $objArray['mbid']                  = $song->mbid;
            $objArray['album_mbid']            = $song->album_mbid;
            $objArray['artist_mbid']           = $song->artist_mbid;
            $objArray['albumartist_mbid']      = $song->albumartist_mbid;
            $objArray['art']                   = $art_url;
            $objArray['flag']                  = (!$flag->get_flag($user->getId()) ? 0 : 1);
            $objArray['preciserating']         = $user_rating;
            $objArray['rating']                = $user_rating;
            $objArray['averagerating']         = $rating->get_average_rating();
            $objArray['playcount']             = (int)$song->total_count;
            $objArray['catalog']               = $song->getCatalogId();
            $objArray['composer']              = $song->composer;
            $objArray['channels']              = $song->channels;
            $objArray['comment']               = $song->comment;
            $objArray['license']               = $licenseLink;
            $objArray['publisher']             = $song->label;
            $objArray['language']              = $song->language;
            $objArray['lyrics']                = $song->lyrics;
            $objArray['replaygain_album_gain'] = $song->replaygain_album_gain;
            $objArray['replaygain_album_peak'] = $song->replaygain_album_peak;
            $objArray['replaygain_track_gain'] = $song->replaygain_track_gain;
            $objArray['replaygain_track_peak'] = $song->replaygain_track_peak;
            $objArray['r128_album_gain']       = $song->r128_album_gain;
            $objArray['r128_track_gain']       = $song->r128_track_gain;

            if (Song::isCustomMetadataEnabled()) {
                foreach ($song->getMetadata() as $metadata) {
                    $meta_name = str_replace(
                        array(' ', '(', ')', '/', '\\', '#'),
                        '_',
                        $metadata->getField()->getName()
                    );
                    $objArray[$meta_name] = $metadata->getData();
                }
            }
            $JSON[] = $objArray;
        } // end foreach

        if ($encode) {
            $output = ($object) ? array("song" => $JSON) : $JSON[0] ?? array();

            return json_encode($output, JSON_PRETTY_PRINT);
        }

        return $JSON;
    }

    /**
     * videos
     *
     * This builds the JSON document for displaying video objects
     *
     * @param int[] $videos Video id's to include
     * @param User $user
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function videos($videos, $user, $object = true): string
    {
        if ((count($videos) > self::$limit || self::$offset > 0) && self::$limit) {
            $videos = array_slice($videos, self::$offset, self::$limit);
        }

        $JSON = [];
        foreach ($videos as $video_id) {
            $video = new Video($video_id);
            if ($video->isNew()) {
                continue;
            }
            $video->format();
            $rating      = new Rating($video_id, 'video');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($video_id, 'video');
            $art_url     = Art::url($video_id, 'video', Core::get_request('auth'));
            $JSON[]      = array(
                "id" => (string)$video->id,
                "title" => $video->title,
                "mime" => $video->mime,
                "resolution" => $video->f_resolution,
                "size" => (int)$video->size,
                "genre" => self::genre_array($video->tags),
                "time" => (int)$video->time,
                "url" => $video->play_url('', 'api', false, $user->getId(), $user->streamtoken),
                "art" => $art_url,
                "flag" => (!$flag->get_flag($user->getId()) ? 0 : 1),
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => $rating->get_average_rating(),
                "playcount" => (int)$video->total_count
            );
        } // end foreach
        $output = ($object) ? array("video" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * democratic
     *
     * This handles creating an JSON document for democratic items, this can be a little complicated
     * due to the votes and all of that
     *
     * @param array $object_ids Object IDs
     * @param User $user
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function democratic($object_ids, $user, $object = true): string
    {
        if (!is_array($object_ids)) {
            $object_ids = array();
        }
        $democratic = Democratic::get_current_playlist($user);

        $JSON = [];
        foreach ($object_ids as $row_id => $data) {
            $className = ObjectTypeToClassNameMapper::map($data['object_type']);
            /** @var Song $song */
            $song = new $className($data['object_id']);
            if ($song->isNew()) {
                continue;
            }
            $song->format();

            $rating      = new Rating($song->id, 'song');
            $user_rating = $rating->get_user_rating($user->getId());
            $art_url     = Art::url($song->album, 'album', $_REQUEST['auth'] ?? '');
            $songMime    = $song->mime;
            $play_url    = $song->play_url('', 'api', false, $user->id, $user->streamtoken);

            $JSON[] = array(
                "id" => (string)$song->id,
                "title" => $song->get_fullname(),
                "artist" => array("id" => (string)$song->artist, "name" => $song->get_artist_fullname()),
                "album" => array("id" => (string)$song->album, "name" => $song->get_album_fullname()),
                "genre" => self::genre_array($song->tags),
                "track" => (int)$song->track,
                "time" => (int)$song->time,
                "mime" => $songMime,
                "url" => $play_url,
                "size" => (int)$song->size,
                "art" => $art_url,
                "preciserating" => $user_rating,
                "rating" => $user_rating,
                "averagerating" => ($rating->get_average_rating() ?? null),
                "playcount" => (int)$song->total_count,
                "vote" => $democratic->get_vote($row_id)
            );
        } // end foreach
        $output = ($object) ? array("song" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * user
     *
     * This handles creating an JSON document for a user
     */
    public static function user(User $user, bool $fullinfo, ?bool $object = true): string
    {
        $user->format();
        if ($fullinfo) {
            $JSON = array(
                "id" => (string) $user->id,
                "username" => $user->username,
                "auth" => $user->apikey,
                "email" => $user->email,
                "access" => (int) $user->access,
                "fullname_public" => (int) $user->fullname_public,
                "validation" => $user->validation,
                "disabled" => (int) $user->disabled,
                "create_date" => (int) $user->create_date,
                "last_seen" => (int) $user->last_seen,
                "website" => $user->website,
                "state" => $user->state,
                "city" => $user->city
            );
        } else {
            $JSON = array(
                "id" => (string) $user->id,
                "username" => $user->username,
                "create_date" => $user->create_date,
                "last_seen" => $user->last_seen,
                "website" => $user->website,
                "state" => $user->state,
                "city" => $user->city
            );
        }

        if ($user->fullname_public) {
            $JSON['fullname'] = $user->fullname;
        }
        $output = ($object) ? array("user" => $JSON) : $JSON;

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * users
     *
     * This handles creating an JSON document for a user list
     *
     * @param int[] $users User id list
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function users($users, $object = true): string
    {
        $JSON = [];
        foreach ($users as $user_id) {
            $user   = new User($user_id);
            $JSON[] = array(
                "id" => (string)$user_id,
                "username" => $user->username
            );
        } // end foreach
        $output = ($object) ? array("user" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * shouts
     *
     * This handles creating an JSON document for a shout list
     *
     * @param Traversable<Shoutbox> $shouts Shout id list
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function shouts(Traversable $shouts, $object = true): string
    {
        $JSON = [];

        /** @var Shoutbox $shout */
        foreach ($shouts as $shout) {
            $user = new User($shout->getUserId());

            $JSON[] = [
                "id" => (string) $shout->getId(),
                "date" => $shout->getDate()->getTimestamp(),
                "text" => $shout->getText(),
                "user" => array(
                    "id" => (string) $user->getId(),
                    "username" => $user->getUsername()
                )
            ];
        }
        $output = ($object) ? array("shout" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * timeline
     *
     * This handles creating an JSON document for an activity list
     *
     * @param int[] $activities Activity id list
     * @param bool $object (whether to return as a named object array or regular array)
     */
    public static function timeline($activities, $object = true): string
    {
        $JSON = array();
        foreach ($activities as $activity_id) {
            $activity = new Useractivity($activity_id);
            $user     = new User($activity->user);
            $objArray = array(
                "id" => (string) $activity_id,
                "date" => $activity->activity_date,
                "object_type" => $activity->object_type,
                "object_id" => (string)$activity->object_id,
                "action" => $activity->action,
                "user" => array(
                    "id" => (string) $activity->user,
                    "username" => $user->username
                )
            );
            $JSON[] = $objArray;
        }
        $output = ($object) ? array("activity" => $JSON) : $JSON[0] ?? array();

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * deleted
     *
     * This handles creating a JSON document for deleted items
     *
     * @param string $object_type ('song', 'podcast_episode', 'video')
     * @param array $objects deleted object list
     */
    public static function deleted($object_type, $objects): string
    {
        if ((count($objects) > self::$limit || self::$offset > 0) && self::$limit) {
            $objects = array_splice($objects, self::$offset, self::$limit);
        }
        $JSON = array();
        foreach ($objects as $row) {
            switch ($object_type) {
                case 'song':
                    $objArray = array(
                        "id" => (string)$row['id'],
                        "addition_time" => $row['addition_time'],
                        "delete_time" => $row['delete_time'],
                        "title" => $row['title'],
                        "file" => $row['file'],
                        "catalog" => $row['catalog'],
                        "total_count" => $row['total_count'],
                        "total_skip" => $row['total_skip'],
                        "update_time" => $row['update_time'],
                        "album" => (string)$row['album'],
                        "artist" => (string)$row['artist']
                    );
                    $JSON[] = $objArray;
                    break;
                case 'podcast_episode':
                    $objArray = array(
                        "id" => (string)$row['id'],
                        "addition_time" => $row['addition_time'],
                        "delete_time" => $row['delete_time'],
                        "title" => $row['title'],
                        "file" => $row['file'],
                        "catalog" => $row['catalog'],
                        "total_count" => $row['total_count'],
                        "total_skip" => $row['total_skip'],
                        "podcast" => (string)$row['podcast']
                    );
                    $JSON[] = $objArray;
                    break;
                case 'video':
                    $objArray = array(
                        "id" => (string)$row['id'],
                        "addition_time" => $row['addition_time'],
                        "delete_time" => $row['delete_time'],
                        "title" => $row['title'],
                        "file" => $row['file'],
                        "catalog" => $row['catalog'],
                        "total_count" => $row['total_count'],
                        "total_skip" => $row['total_skip']
                    );
                    $JSON[] = $objArray;
            }
        }
        $output = array("deleted_" . $object_type => $JSON);

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getSongRepository(): SongRepositoryInterface
    {
        global $dic;

        return $dic->get(SongRepositoryInterface::class);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getAlbumRepository(): AlbumRepositoryInterface
    {
        global $dic;

        return $dic->get(AlbumRepositoryInterface::class);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getPodcastRepository(): PodcastRepositoryInterface
    {
        global $dic;

        return $dic->get(PodcastRepositoryInterface::class);
    }

    /**
     * @deprecated Inject by constructor
     */
    private static function getLicenseRepository(): LicenseRepositoryInterface
    {
        global $dic;

        return $dic->get(LicenseRepositoryInterface::class);
    }
}
