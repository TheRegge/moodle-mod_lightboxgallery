<?php

    require_once($CFG->libdir . '/gdlib.php');

    define('THUMBNAIL_WIDTH', 162);
    define('THUMBNAIL_HEIGHT', 132);

    class lightboxgallery_image {

        private $cm;
        private $cmid;
        private $stored_file;
        private $image_url;
        private $tags;
        private $thumb_url;

        public function __construct($stored_file, $gallery, $cm) {
            global $CFG;

            $this->stored_file = &$stored_file;
            $this->gallery = &$gallery;
            $this->cm = &$cm;
            $this->cmid = $cm->id;

            if(!$this->stored_file->is_valid_image()) {
              // error? continue;
            }

            $this->image_url = $CFG->wwwroot.'/pluginfile.php/'.$cm->id.'/mod_lightboxgallery/gallery_images/'.$this->stored_file->get_itemid().$this->stored_file->get_filepath().$this->stored_file->get_filename();
            $this->thumb_url = $CFG->wwwroot.'/pluginfile.php/'.$cm->id.'/mod_lightboxgallery/gallery_thumbs/0/'.$this->stored_file->get_filepath().$this->stored_file->get_filename().'.png';
 
            $image_info = $this->stored_file->get_imageinfo();

            $this->height = $image_info['height'];
            $this->width = $image_info['width'];

            if(!$this->thumbnail = $this->get_thumbnail()) {
                $this->create_thumbnail();
            }
        }

        public function add_tag($tag) {
            global $DB;

            $imagemeta = new stdClass();
            $imagemeta->gallery = $this->cm->instance;
            $imagemeta->image = $this->stored_file->get_pathnamehash();
            $imagemeta->metatype = 'tag';
            $imagemeta->description = $tag;

            return $DB->insert_record('lightboxgallery_image_meta', $imagemeta);
        }

        private function create_thumbnail() {
            global $CFG;

            $fileinfo = array(
                'contextid'	=> $this->cmid,
                'component'	=> 'mod_lightboxgallery',
                'filearea'	=> 'gallery_thumbs',
                'itemid'	=> 0,
                'filepath'	=> '/',
                'filename'	=> $this->stored_file->get_filename().'.png');

            ob_start();
            imagepng($this->get_image_resized());
            $thumbnail = ob_get_clean();

            $fs = get_file_storage();
            $fs->create_file_from_string($fileinfo, $thumbnail);

            return;
        }

        private function delete_file() {
            $this->delete_thumbnail();
            $this->stored_file->delete();
        }

        public function delete_tag($tag) {
            global $DB;

            return $DB->delete_records('lightboxgallery_image_meta', array('gallery' => $this->cm->instance, 'image' => $this->stored_file->get_pathnamehash(), 'metatype' => 'tag', 'description' => $tag));
        }

        private function delete_thumbnail() {
          $this->thumbnail->delete();
        }

        public function flip_image($direction) {

            $fileinfo = array(
                'contextid'     => $this->cmid,
                'component'     => 'mod_lightboxgallery',
                'filearea'      => 'gallery_images',
                'itemid'        => 0,
                'filepath'      => '/',
                'filename'      => $this->stored_file->get_filename());

            ob_start();
            imagejpeg($this->get_image_flipped($direction));
            $flipped = ob_get_clean();

            $this->delete_file();
            $fs = get_file_storage();
            $fs->create_file_from_string($fileinfo, $flipped);

            $this->create_thumbnail();
        }

        private function get_editing_options() {
            global $CFG;

            $html = '<form action="'.$CFG->wwwroot.'/mod/lightboxgallery/imageedit.php" method="post"/>'.
                        '<input type="hidden" name="id" value="'.$this->cmid.'" />'.
                        '<input type="hidden" name="image" value="'.$this->stored_file->get_filename().'" />'.
                        '<input type="hidden" name="page" value="0" />'.
                        '<select name="tab" class="lightbox-edit-select" onchange="submit();">'.
                            '<option disabled selected>Choose...</option>'.
                            '<option value="caption">Caption</option>'.
                            '<!--<option value="crop">Crop</option>-->'.
                            '<option value="delete">Delete</option>'.
                            '<option value="flip">Flip</option>'.
                            '<option value="resize">Resize</option>'.
                            '<option value="rotate">Rotate</option>'.
                            '<option value="tag">Tag</option>'.
                            '<option value="thumbnail">Thumbnail</option>'.
                        '</select>'.
                    '</form>';

            return $html;
        }

        public function get_image_caption() {
            global $DB;
            $caption = '';

            if($image_meta = $DB->get_record('lightboxgallery_image_meta', array('image' => $this->stored_file->get_pathnamehash(), 'metatype' => 'caption'))) {
                $caption = $image_meta->description;
            }

            return $caption;
        }

        public function get_image_display_html($editing = true) {
            $caption = $this->get_image_caption();
            $timemodified = strftime(get_string('strftimedatetimeshort', 'langconfig'), $this->stored_file->get_timemodified());
            $filesize = round($this->stored_file->get_filesize() / 100) / 10;

            $width = round(100 / $this->gallery->perrow);

            $html = '<div class="lightbox-gallery-image-container" style="width: '.$width.'%;">'.
                        '<div class="lightbox-gallery-image-wrapper">'.
                            '<div class="lightbox-gallery-image-frame">'.
                                '<a class="lightbox-gallery-image-thumbnail" href="'.$this->image_url.'"rel="lightbox[gallery]" title="'.$caption.'" style="background-image: url(\''.$this->thumb_url.'\'); width: '.THUMBNAIL_WIDTH.'px; height: '.THUMBNAIL_HEIGHT.'px;"></a>'.
                                '<div class="lightbox-gallery-image-caption">'.$caption.'</div>'.
                                ($this->gallery->extinfo ? '<div class="lightbox-gallery-image-extinfo">'.$timemodified.'<br/>'.$filesize.'KB '.$this->width.'x'.$this->height.'px</div>' : '').
                                ($editing ? $this->get_editing_options() : '').
                            '</div>'.
                        '</div>'.
                    '</div>';

            return $html;

        }

        private function get_image_flipped($direction) {
            $image = imagecreatefromstring($this->stored_file->get_content());
            $flipped = imagecreatetruecolor($this->width, $this->height);

            if($direction == 'horizontal') {
                for ($x = 0; $x < $w; $x++) {
                    for ($y = 0; $y < $h; $y++) {
                        imagecopy($flipped, $image, $x, $h - $y - 1, $x, $y, 1, 1);
                    }
                }
            } else {
                for ($x = 0; $x < $w; $x++) {
                    for ($y = 0; $y < $h; $y++) {
                        imagecopy($flipped, $image, $w - $x - 1, $y, $x, $y, 1, 1);
                    }
                }
            }

            return $flipped;

        }

        private function get_image_resized($height = THUMBNAIL_HEIGHT, $width = THUMBNAIL_WIDTH, $offsetx = 0, $offsety = 0) {
            $image = imagecreatefromstring($this->stored_file->get_content());
            $resized = imagecreatetruecolor($width, $height);

            $cx = $this->width / 2;
            $cy = $this->height / 2;

            $ratiow = $width / $this->width;
            $ratioh = $height / $this->height;

            if ($ratiow < $ratioh) {
                $srcw = floor($width / $ratioh);
                $srch = $this->height;
                $srcx = floor($cx - ($srcw / 2)) + $offsetx;
                $srcy = $offsety;
            } else {
                $srcw = $this->width;
                $srch = floor($height / $ratiow);
                $srcx = $offsetx;
                $srcy = floor($cy - ($srch / 2)) + $offsety;
            }

            imagecopybicubic($resized, $image, 0, 0, $srcx, $srcy, $width, $height, $srcw, $srch);

            return $resized;

        }

        private function get_image_rotated($angle) {
            $image = imagecreatefromstring($this->stored_file->get_content());
            $rotated = imagerotate($image, $angle, 0);
 
            return $rotated;
        }

        public function get_tags() {
            global $DB;

            if(isset($this->tags)) {
                return $this->tags;
            }

            $this->tags = $DB->get_records('lightboxgallery_image_meta', array('image' => $this->stored_file->get_pathnamehash(), 'metatype' => 'tag'));

            return $this->tags;
        }

        private function get_thumbnail() {
            $fs = get_file_storage();

            if($thumbnail = $fs->get_file($this->cmid, 'mod_lightboxgallery', 'gallery_thumbs', '0', '/', $this->stored_file->get_filename().'.png')) {
                return $thumbnail;
            }

            return false;
        }

        public function get_thumbnail_url() {
            return $this->thumb_url;
        }

        public function resize_image($width, $height) {
            $fileinfo = array(
                'contextid'     => $this->cmid,
                'component'     => 'mod_lightboxgallery',
                'filearea'      => 'gallery_images',
                'itemid'        => 0,
                'filepath'      => '/',
                'filename'      => $this->stored_file->get_filename());

            ob_start();
            imagejpeg($this->get_image_resized($height, $width));
            $resized = ob_get_clean();

            $this->delete_file();
            $fs = get_file_storage();
            $fs->create_file_from_string($fileinfo, $resized);

            $this->create_thumbnail();
        }

        public function rotate_image($angle) {
            $fileinfo = array(
                'contextid'     => $this->cmid,
                'component'     => 'mod_lightboxgallery',
                'filearea'      => 'gallery_images',
                'itemid'        => 0,
                'filepath'      => '/',
                'filename'      => $this->stored_file->get_filename());

            ob_start();
            imagejpeg($this->get_image_rotated($angle));
            $rotated = ob_get_clean();

            $this->delete_file();
            $fs = get_file_storage();
            $fs->create_file_from_string($fileinfo, $rotated);

            $this->create_thumbnail();
        }

        public function set_caption($caption) {
            global $DB;

            $imagemeta = new stdClass();
            $imagemeta->gallery = $this->cm->instance;
            $imagemeta->image = $this->stored_file->get_pathnamehash();
            $imagemeta->metatype = 'caption';
            $imagemeta->description = $caption;

            if($meta = $DB->get_record('lightboxgallery_image_meta', array('gallery' => $this->cm->instance, 'image' => $this->stored_file->get_pathnamehash(), 'metatype' => 'caption'))) {
                $imagemeta->id = $meta->id;
                return $DB->update_record('lightboxgallery_image_meta', $imagemeta);
            } else {
                return $DB->insert_record('lightboxgallery_image_meta', $imagemeta);
            }
        }
    }

?>