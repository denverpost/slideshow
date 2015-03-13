# Slideshow Exporter
Exports Slideshow Pro (SSP) slideshows into SmugMug

## Setup
This script requires these environment variables:
```
export SSP_API_KEY=''
export SSP_API_PATH=''
export SMUG_API_KEY=''
export SMUG_SECRET=''
export SMUG_TOKEN=''
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
