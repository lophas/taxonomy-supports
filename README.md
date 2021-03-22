Description: Adds taxonomy supports for date, author, thumbnail, editor and meta_boxes
<<<<<<< HEAD
Version: 2.9
=======
Version: 2.8
>>>>>>> 6fd0e0572bd63676ad665d9ffa89596eddc81d5d
Author: Attila Seres

Features:
- Same UI for editing terms as the builtin post editor
- Utilizes the standard Wordpress metabox system with postbox drag&drop, open/close functionality
- 3rd party plugin fields and addons automatically converted to metaboxes
- Custom Fields metabox for Terms provides same meta field management as the builtin metabox for posts
- Admin menu to select applicable taxonomies

Howto register your own metaboxes:
use the builtin add_meta_box function, you may use following hooks:
do_action( 'add_termmeta_boxes', string $taxonomy, WP_Term $term )
do_action( "add_termmeta_boxes_{$taxonomy}", WP_Term $term )
