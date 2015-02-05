#!/bin/bash

rsync --recursive --progress --exclude='.git*' --exclude='.DS_Store' --exclude='assets' . ~/Projects/wordpress/svn/wp-quadratum/trunk/
rsync --recursive --progress --exclude='.git*' --exclude='.DS_Store' --exclude='upload' --exclude='*.pxm' ./assets/* ~/Projects/wordpress/svn/wp-quadratum/assets/
