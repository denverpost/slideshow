/* Adding Quicktag buttons to the editor Wordpress ver. 3.3 and above
    * - Button HTML ID (required)
    * - Button display, value="" attribute (required)
    *   * - Opening Tag (required)
    *   * - Closing Tag (required)
    *   * - Access key, accesskey="" attribute for the button (optional)
    *   * - Title, title="" attribute (optional)
    *   * - Priority/position on bar, 1-9 = first, 11-19 = second, 21-29 = third, etc. (optional)
    *   */
	QTags.addButton( 'mc_smugMug', '[insertSmugmug]', '[insertSmugmug]' );
  QTags.addButton( 'mc_longForm', '[insertlongForm]', '[insertLongForm]' );
	QTags.addButton( 'insertVidDPO', 'Denver Post Video', '[insertVidDPO vidid=', ']' );
	QTags.addButton( 'insertVidMerc', 'Mercury News Video', '[insertVidMerc vidid=', ']' );
	QTags.addButton( 'insertVidTC', 'Twin Cities Video', '[insertVidTC vidid=', ']' );
