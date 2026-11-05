<?php
/** Exercise the installed exact ZIP in a disposable WordPress site. */

declare( strict_types=1 );

$port = getenv( 'DIGICYFR_TEST_PORT' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $port;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PORT'] = $port;
$_SERVER['HTTPS'] = 'off';

require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function input( array $change = array() ): array {
	return array_merge( array(
		'scope_kind' => 'journey', 'scope_label' => 'Kup bilet', 'scope_post_id' => '', 'scope_url' => home_url( '/buy-ticket/' ),
		'test_key' => 'keyboard', 'outcome' => 'not_tested', 'assignee_user_id' => '0', 'tested_on' => '', 'note' => '', 'evidence_url' => '',
	), $change );
}

$plugin = 'digicyfr-accessibility-checklist/digicyfr-accessibility-checklist.php';
check( is_plugin_active( $plugin ) && '0.1.1' === DIGICYFR_ACCESSIBILITY_CHECKLIST_VERSION, 'exact ZIP active and versioned' );
$admin = get_user_by( 'login', 'digicyfr_test_admin' );
check( $admin instanceof WP_User, 'test administrator exists' );
wp_set_current_user( $admin->ID );
$cpt = get_post_type_object( DACL_Model::TYPE );
check( $cpt && ! $cpt->public && ! $cpt->publicly_queryable && ! $cpt->show_ui && ! $cpt->show_in_rest && ! $cpt->can_export && ! $cpt->rewrite && ! $cpt->query_var, 'CPT has no public, REST, core admin or export route' );
check( 'manage_options' === $cpt->cap->read_post && 'manage_options' === $cpt->cap->edit_post && 'manage_options' === $cpt->cap->delete_post, 'mapped CPT actions require administrator capability' );
check( load_textdomain( 'digicyfr-accessibility-checklist', WP_PLUGIN_DIR . '/digicyfr-accessibility-checklist/languages/digicyfr-accessibility-checklist-pl_PL.mo' ), 'bundled Polish catalog loads' );
check( 'Lista kontrolna dostępności' === __( 'Accessibility Checklist', 'digicyfr-accessibility-checklist' ) && 'Nie testowano' === DACL_Model::outcomes()['not_tested'], 'Polish menu and outcome strings' );
unload_textdomain( 'digicyfr-accessibility-checklist' );
check( 6 === count( DACL_Model::checks() ) && 4 === count( DACL_Model::outcomes() ), 'six fixed human checks and four outcomes' );
foreach ( DACL_Model::checks() as $key => $check ) {
	check( str_starts_with( $check[2], 'https://www.w3.org/' ) && '' !== $check[1], 'guided check has instruction and W3C link: ' . $key );
}

$invalid_urls = array( 'javascript:alert(1)', 'data:text/plain,hi', '//example.org/x', 'https://user:pass@example.org/x', 'https://example.org/x', 'https://127.0.0.1.evil.test/x', 'not-a-url' );
foreach ( $invalid_urls as $url ) {
	check( '' === DACL_Model::valid_url( $url, true ), 'invalid or external scope URL rejected: ' . $url );
}
check( home_url( '/x' ) === DACL_Model::valid_url( home_url( '/x' ), true ), 'same-site full URL accepted' );
check( 'https://tracker.example.org/issue?id=12' === DACL_Model::valid_url( 'https://tracker.example.org/issue?id=12', false ), 'external evidence URL allowed' );
check( '' === DACL_Model::valid_url( 'https://user:pass@tracker.example.org/x', false ), 'credentialed evidence URL rejected' );
check( "'=1" === DACL_Admin::csv_safe( '=1' ) && "'  +1" === DACL_Admin::csv_safe( '  +1' ) && "'\t@x" === DACL_Admin::csv_safe( "\t@x" ) && "'-1" === DACL_Admin::csv_safe( '-1' ), 'formula guard covers operators and leading whitespace' );

$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Public page' ), true );
check( is_int( $page_id ) && $page_id > 0, 'published page exists' );
$bad_permalink = static function ( string $url, int $post_id ) use ( $page_id ): string { return $post_id === $page_id ? 'https://external.example/x' : $url; };
add_filter( 'page_link', $bad_permalink, 10, 2 );
check( is_wp_error( DACL_Model::validate( input( array( 'scope_kind' => 'page', 'scope_post_id' => (string) $page_id, 'scope_url' => '' ) ) ) ), 'filtered external page permalink rejected instead of empty snapshot' );
remove_filter( 'page_link', $bad_permalink, 10 );
$page = DACL_Model::save( input( array( 'scope_kind' => 'page', 'scope_label' => 'Public page', 'scope_post_id' => (string) $page_id, 'scope_url' => '' ) ) );
check( is_int( $page ) && get_permalink( $page_id ) === DACL_Model::get( $page )['scope_url'], 'page ID stores observed same-site permalink snapshot' );
$journey = DACL_Model::save( input() );
check( is_int( $journey ) && 'not_tested' === DACL_Model::get( $journey )['outcome'] && '' === DACL_Model::get( $journey )['tested_on'] && 0 === (int) DACL_Model::get( $journey )['tester_user_id'], 'not-tested record has no date or tester' );
check( 'private' === get_post_status( $journey ) && DACL_Model::TYPE === get_post_type( $journey ), 'record is a private CPT post' );
check( is_wp_error( DACL_Model::save( input( array( 'outcome' => 'needs_work', 'tested_on' => current_datetime()->format( 'Y-m-d' ) ) ) ) ), 'needs-work requires note' );
check( is_wp_error( DACL_Model::save( input( array( 'outcome' => 'not_applicable', 'tested_on' => current_datetime()->format( 'Y-m-d' ) ) ) ) ), 'not-applicable requires reason' );
check( is_wp_error( DACL_Model::save( input( array( 'outcome' => 'pass', 'tested_on' => '2027-02-30' ) ) ) ), 'impossible date rejected' );
check( is_wp_error( DACL_Model::save( input( array( 'outcome' => 'pass', 'tested_on' => current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ) ) ) ) ), 'future date rejected' );
check( is_wp_error( DACL_Model::save( input( array( 'outcome' => 'not_tested', 'tested_on' => current_datetime()->format( 'Y-m-d' ) ) ) ) ), 'not-tested date rejected' );
check( is_wp_error( DACL_Model::save( input( array( 'scope_url' => 'https://external.example/x' ) ) ) ), 'external journey start rejected' );
check( is_wp_error( DACL_Model::save( input( array( 'evidence_url' => 'javascript:alert(1)' ) ) ) ), 'unsafe evidence rejected' );

$today = current_datetime()->format( 'Y-m-d' );
$saved = DACL_Model::save( input( array( 'outcome' => 'pass', 'tested_on' => $today, 'evidence_url' => 'https://tracker.example.org/issue?id=12', 'note' => 'Sprawdź C:\\folder' ) ), $journey );
check( $journey === $saved && 'pass' === DACL_Model::get( $journey )['outcome'] && $today === DACL_Model::get( $journey )['tested_on'] && $admin->ID === (int) DACL_Model::get( $journey )['tester_user_id'] && 'Sprawdź C:\\folder' === DACL_Model::get( $journey )['note'], 'completed record stamps tester/date and preserves literal backslash' );
$second_admin_id = wp_create_user( 'dacl_second_admin', 'test-password-only', 'second@example.invalid' );
( new WP_User( $second_admin_id ) )->set_role( 'administrator' );
wp_set_current_user( $second_admin_id );
$corrected = DACL_Model::save( input( array( 'outcome' => 'pass', 'tested_on' => $today, 'note' => 'Corrected note', 'evidence_url' => 'https://tracker.example.org/issue?id=12' ) ), $journey );
check( $journey === $corrected && $admin->ID === (int) DACL_Model::get( $journey )['tester_user_id'], 'later note correction preserves original tester' );
$retested = DACL_Model::save( input( array( 'outcome' => 'needs_work', 'tested_on' => $today, 'note' => 'Keyboard trap at dialog', 'evidence_url' => 'https://tracker.example.org/issue?id=12' ) ), $journey );
check( $journey === $retested && $second_admin_id === (int) DACL_Model::get( $journey )['tester_user_id'], 'changed outcome stamps the current tester' );
wp_set_current_user( $admin->ID );
$repeat = DACL_Model::save( input( array( 'outcome' => 'pass', 'tested_on' => $today ) ) );
check( is_int( $repeat ) && $repeat !== $journey, 'repeat test creates a separate observation' );
foreach ( array_keys( DACL_Model::checks() ) as $key ) {
	check( is_int( DACL_Model::save( input( array( 'test_key' => $key, 'scope_label' => 'Fixture ' . $key ) ) ) ), 'saved check key ' . $key );
}

$assignee_id = wp_create_user( 'dacl_assignee', 'test-password-only', 'assignee@example.invalid' );
wp_update_user( array( 'ID' => $assignee_id, 'display_name' => '+SUM(1)' ) );
$formula = DACL_Model::save( input( array( 'scope_label' => '=HYPERLINK("https://evil.invalid")', 'note' => "  @note, \"Polski\"\nLinia 2", 'assignee_user_id' => (string) $assignee_id, 'outcome' => 'needs_work', 'tested_on' => $today ) ) );
check( is_int( $formula ), 'formula and multiline fixture saved' );
check( is_wp_error( DACL_Model::save( input( array( 'assignee_user_id' => '9999999' ) ) ) ), 'missing assignee rejected' );
$filters = DACL_Admin::filters( array( 'scope_kind' => 'journey', 'outcome' => 'needs_work' ) );
$query = new WP_Query( DACL_Admin::query_args( $filters, 1, 50 ) );
check( 2 === count( $query->posts ), 'list filters select exactly matching observations' );
$stream = fopen( 'php://temp', 'w+' );
check( true === DACL_Admin::stream_csv( $stream, $filters ), 'filtered CSV streams successfully' );
rewind( $stream );
$csv = array();
while ( false !== ( $row = fgetcsv( $stream, 0, ',', '"', '' ) ) ) { $csv[] = $row; }
fclose( $stream );
check( 3 === count( $csv ) && 19 === count( $csv[0]) && $csv[0][0] === 'record_id', 'CSV fixed header and exactly filtered rows' );
$formula_row = $csv[1][0] === (string) $formula ? $csv[1] : $csv[2];
check( str_starts_with( $formula_row[2], "'=HYPERLINK(" ) && "'+SUM(1)" === $formula_row[10] && str_contains( $formula_row[14], "\nLinia 2" ) && str_contains( $formula_row[14], 'Polski' ), 'CSV protects title and user name, preserves commas quotes and newlines' );
$too_many = static function (): int { return 20001; };
add_filter( 'found_posts', $too_many );
$cap_stream = fopen( 'php://temp', 'w+' );
check( is_wp_error( DACL_Admin::stream_csv( $cap_stream, DACL_Admin::filters( array() ) ) ), '20k CSV cap rejects rather than truncates' );
fclose( $cap_stream );
remove_filter( 'found_posts', $too_many );

for ( $n = 0; $n < 52; ++$n ) {
	DACL_Model::save( input( array( 'scope_label' => 'Batch ' . $n ) ) );
}
$all = DACL_Admin::filters( array() );
$page_one = new WP_Query( DACL_Admin::query_args( $all, 1, 50 ) );
$page_two = new WP_Query( DACL_Admin::query_args( $all, 2, 50 ) );
check( 50 === count( $page_one->posts ) && count( $page_two->posts ) >= 10, 'list is paginated at 50 rows' );

$editor_id = wp_create_user( 'dacl_editor', 'test-password-only', 'editor@example.invalid' );
( new WP_User( $editor_id ) )->set_role( 'editor' );
wp_set_current_user( $editor_id );
check( null === DACL_Model::get( $journey ) && is_wp_error( DACL_Model::save( input(), $journey ) ) && ! DACL_Model::delete( $journey ), 'editor cannot read/edit/delete private observation' );
$denied_stream = fopen( 'php://temp', 'w+' );
check( is_wp_error( DACL_Admin::stream_csv( $denied_stream, $all ) ), 'editor cannot export' );
fclose( $denied_stream );
wp_set_current_user( 0 );
check( null === DACL_Model::get( $journey ) && is_wp_error( DACL_Model::save( input() ) ), 'unauthenticated user cannot read or save' );
wp_set_current_user( $admin->ID );
$foreign = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Not a checklist row' ), true );
check( null === DACL_Model::get( $foreign ) && is_wp_error( DACL_Model::save( input(), $foreign ) ) && ! DACL_Model::delete( $foreign ), 'other post type cannot be edited or deleted' );
function dacl_test_die( $message ): void { throw new RuntimeException( wp_strip_all_tags( (string) $message ) ); }
$die_filter = static function (): string { return 'dacl_test_die'; };
add_filter( 'wp_die_handler', $die_filter );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'action' => 'dacl_delete', 'record_id' => (string) $journey, '_wpnonce' => wp_create_nonce( 'dacl_delete_' . $journey ) );
wp_set_current_user( $editor_id );
$cap_denied = false;
try { DACL_Admin::handle(); } catch ( RuntimeException $error ) { $cap_denied = true; }
check( $cap_denied && null !== get_post( $journey ), 'direct delete with valid nonce still denies editor' );
wp_set_current_user( $admin->ID );
$_POST['_wpnonce'] = 'invalid';
$nonce_denied = false;
try { DACL_Admin::handle(); } catch ( RuntimeException $error ) { $nonce_denied = true; }
check( $nonce_denied && null !== get_post( $journey ), 'direct delete with administrator but invalid nonce is denied' );
remove_filter( 'wp_die_handler', $die_filter );
$_POST = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$error_token = 'invalid123';
set_transient( 'dacl_form_' . $admin->ID . '_' . $error_token, array( 'input' => input( array( 'outcome' => 'bad' ) ), 'errors' => array( 'outcome' => 'Choose a recorded outcome.' ) ), 300 );
$_GET = array( 'page' => 'digicyfr-accessibility-checklist', 'dacl_view' => 'new', 'dacl_state' => $error_token );
ob_start();
DACL_Admin::screen();
$invalid_form = ob_get_clean();
check( str_contains( $invalid_form, 'class="notice notice-error dacl-errors"' ) && 4 === preg_match_all( '/<input type="radio"[^>]*aria-invalid="true" aria-describedby="dacl-error-outcome"/', $invalid_form ), 'invalid outcome form announces summary and associates all radio controls with error' );
check( str_contains( $invalid_form, 'class="dacl-guidance" role="group" aria-label="Human check instructions"' ), 'guided instructions expose a named group to assistive technology' );
wp_delete_post( $page_id, true );
$_GET = array( 'page' => 'digicyfr-accessibility-checklist', 'scope_search' => 'Public page' );
ob_start();
DACL_Admin::screen();
$rendered = ob_get_clean();
check( str_contains( $rendered, 'Source removed' ) && ! str_contains( $rendered, '<script>' ), 'deleted source clearly labelled; output escaped' );
update_option( 'timezone_string', 'Europe/Warsaw' );
$_GET = array( 'page' => 'digicyfr-accessibility-checklist', 'dacl_view' => 'edit', 'record_id' => $journey );
ob_start();
DACL_Admin::screen();
$edit_form = ob_get_clean();
$site_created = wp_date( 'Y-m-d H:i', strtotime( DACL_Model::get( $journey )['created_at_utc'] . ' UTC' ) );
check( str_contains( $edit_form, 'Created (site time):' ) && str_contains( $edit_form, $site_created ) && str_contains( $edit_form, 'External evidence (opens in a new tab)' ) && str_contains( $edit_form, 'rel="noopener noreferrer"' ), 'site-local timestamps and safe external evidence link displayed' );
wp_delete_user( $assignee_id, $admin->ID );
check( 'Former user #' . $assignee_id === DACL_Model::user_name( $assignee_id ), 'deleted assignee retains stable ID state' );
check( DACL_Model::delete( $repeat ) && null === DACL_Model::get( $repeat ), 'administrator can permanently delete one record' );
wp_trash_post( $formula );
check( 'trash' === get_post_status( $formula ), 'fixture record moved to trash before uninstall' );
deactivate_plugins( $plugin );
check( DACL_Model::TYPE === get_post_type( $journey ) && '' !== get_post_meta( $journey, '_dacl_note', true ), 'deactivation retains private records' );
activate_plugin( $plugin );
define( 'WP_UNINSTALL_PLUGIN', $plugin );
require WP_PLUGIN_DIR . '/digicyfr-accessibility-checklist/uninstall.php';
check( null === get_post( $journey ) && null === get_post( $formula ) && '' === get_post_meta( $journey, '_dacl_note', true ), 'uninstall erases private and trashed observations with notes' );
printf( "PASS: exact ZIP accessibility checklist integration (WP %s, PHP %s)\n", get_bloginfo( 'version' ), PHP_VERSION );
