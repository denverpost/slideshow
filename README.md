# Slideshow Exporter
Exports Slideshow Pro (SSP) slideshows into SmugMug

## Setup
This script requires to environment variables (in case it's not obvious, the two below are fake):
```
  export API_KEY='hosted-sdfasdfasdfasdfasdfasd'
  export API_PATH='something.slideshowpro.com'
```

### Things That Will Cause Errors Unless You Address Them Head-On
* You'll need to create a dir named `smugcache`
* You'll need to have these tables in a database somewhere:
```
CREATE TABLE `sspexport` (
`sspid` varchar( 255 ) NOT NULL ,
`smugid` int( 11 ) NOT NULL ,
`smugkey` varchar( 255 ) NOT NULL ,
`totalphotos` int( 11 ) NOT NULL ,
`currentphotos` int( 11 ) NOT NULL ,
`status` varchar( 255 ) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = latin1;
CREATE TABLE `mcexport` (
`xmlpath` varchar( 255 ) NOT NULL ,
`smugid` int( 11 ) NOT NULL ,
`smugkey` varchar( 255 ) NOT NULL ,
`totalphotos` int( 11 ) NOT NULL ,
`currentphotos` int( 11 ) NOT NULL ,
`status` varchar( 255 ) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = latin1;
```
