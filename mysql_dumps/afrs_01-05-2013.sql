-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jan 05, 2013 at 08:30 PM
-- Server version: 5.5.28
-- PHP Version: 5.3.10-1ubuntu3.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `afrs`
--

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE IF NOT EXISTS `blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(100) DEFAULT NULL,
  `public_ip_address` varchar(16) NOT NULL,
  `private_ip_address` varchar(16) NOT NULL,
  `datetime_added` datetime NOT NULL,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_priv_pub_ip` (`public_ip_address`,`private_ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE IF NOT EXISTS `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_number` varchar(255) NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `manufacturer` varchar(255) NOT NULL,
  `is_removable` tinyint(1) NOT NULL,
  `size` bigint(11) NOT NULL,
  `free_space` bigint(20) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_number` (`model_number`,`serial_number`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=25 ;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `model_number`, `serial_number`, `manufacturer`, `is_removable`, `size`, `free_space`, `date_added`) VALUES
(24, '', '', '-', 0, 416768512000, 213431922688, '2012-05-23 22:22:38');

-- --------------------------------------------------------

--
-- Table structure for table `errorcodes`
--

CREATE TABLE IF NOT EXISTS `errorcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `error_code` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `error_code` (`error_code`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11 ;

--
-- Dumping data for table `errorcodes`
--

INSERT INTO `errorcodes` (`id`, `error_code`, `description`) VALUES
(1, '10', 'Incorrect membership password.'),
(2, '11', 'Sync point already exists.'),
(3, '12', 'Share name already exists.'),
(4, '13', 'You are not a registered member server.  Please use the REGISTER command to become a member server.'),
(5, '14', 'No shares available.'),
(6, '15', 'Database operation failed.'),
(7, '16', 'Client ID not found.'),
(8, '17', 'Database entry already exists.'),
(9, '18', 'Failed to register client as a member server.'),
(10, '19', 'Database entry does not exist.');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kernel_number` int(11) NOT NULL,
  `inotify_event_name` varchar(50) NOT NULL,
  `short_event_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COMMENT='Event names and their mappings to other names' AUTO_INCREMENT=28 ;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `kernel_number`, `inotify_event_name`, `short_event_name`) VALUES
(1, 4, 'in_attrib', 'attribute'),
(2, 8, 'in_close_write', 'close_write'),
(3, 16, 'in_close_nowrite', 'close_nowrite'),
(4, 32, 'in_create', 'create'),
(5, 512, 'in_delete', 'delete'),
(6, 1024, 'in_delete_self', 'delete_self'),
(7, 2, 'in_modify', 'modify'),
(8, 2048, 'in_move_self', 'moved_self'),
(9, 64, 'in_moved_from', 'moved_from'),
(10, 128, 'in_moved_to', 'moved_to'),
(11, 32, 'in_open', 'open'),
(12, 4095, 'in_all_events', 'all_events'),
(13, 192, 'in_move', 'move'),
(14, 24, 'in_close', 'close');

-- --------------------------------------------------------

--
-- Table structure for table `events_watches`
--

CREATE TABLE IF NOT EXISTS `events_watches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `events_id` int(11) unsigned DEFAULT NULL,
  `watches_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_276d06f942f18f94a4720eeea3c3cf5bbf915ab8` (`events_id`,`watches_id`),
  KEY `index_for_events_watches_events_id` (`events_id`),
  KEY `index_for_events_watches_watches_id` (`watches_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=30 ;

-- --------------------------------------------------------

--
-- Table structure for table `filesystems`
--

CREATE TABLE IF NOT EXISTS `filesystems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fs_type` varchar(50) NOT NULL COMMENT 'name of the filesystem, eg. ext3',
  `is_supported` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=57 ;

--
-- Dumping data for table `filesystems`
--

INSERT INTO `filesystems` (`id`, `fs_type`, `is_supported`) VALUES
(1, 'ext2', 1),
(2, 'ext3', 1),
(3, 'xfs', 0),
(4, 'vfat', 1),
(5, 'iso9660', 1),
(6, '9p', 1),
(7, 'adfs', 1),
(8, 'affs', 1),
(9, 'afs', 1),
(10, 'autofs4', 1),
(11, 'befs', 1),
(12, 'bfs', 1),
(13, 'binfmt_misc.ko', 1),
(14, 'btrfs', 1),
(15, 'cachefiles', 1),
(16, 'ceph', 1),
(17, 'cifs', 1),
(18, 'coda', 1),
(19, 'configfs', 1),
(20, 'cramfs', 1),
(21, 'dlm', 1),
(22, 'efs', 1),
(23, 'exofs', 1),
(24, 'fat', 1),
(25, 'freevxfs', 1),
(26, 'fscache', 1),
(27, 'fuse', 1),
(28, 'gfs2', 1),
(29, 'hfs', 1),
(30, 'hfsplus', 1),
(31, 'hpfs', 1),
(32, 'isofs', 1),
(33, 'jffs2', 1),
(34, 'jfs', 1),
(35, 'lockd', 1),
(36, 'minix', 1),
(37, 'ncpfs', 1),
(38, 'nfs', 1),
(39, 'nfs_common', 1),
(40, 'nfsd', 1),
(41, 'nilfs2', 1),
(42, 'nls', 1),
(43, 'ntfs', 1),
(44, 'ocfs2', 1),
(45, 'omfs', 1),
(46, 'overlayfs', 1),
(47, 'qnx4', 1),
(48, 'quota', 1),
(49, 'reiserfs', 1),
(50, 'romfs', 1),
(51, 'squashfs', 1),
(52, 'sysv', 1),
(53, 'ubifs', 1),
(54, 'udf', 1),
(55, 'ufs', 1),
(56, 'ext4', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inetdevices`
--

CREATE TABLE IF NOT EXISTS `inetdevices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_name` varchar(50) NOT NULL,
  `mac` varchar(17) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `public_ip` varchar(15) NOT NULL,
  `broadcast` varchar(15) NOT NULL,
  `subnet_mask` varchar(15) NOT NULL,
  `gateway` varchar(15) NOT NULL,
  `dns1` varchar(15) NOT NULL,
  `fqdn` varchar(50) NOT NULL,
  `speed` enum('10hd','10fd','100hd','100fd','1000hd','1000fd','10000hd','10000fd') NOT NULL,
  `manufact_info` text,
  `status` tinyint(1) NOT NULL,
  `link_state` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac` (`mac`),
  UNIQUE KEY `ip` (`ip`,`public_ip`),
  UNIQUE KEY `fqdn` (`fqdn`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `inetdevices`
--

INSERT INTO `inetdevices` (`id`, `device_name`, `mac`, `ip`, `public_ip`, `broadcast`, `subnet_mask`, `gateway`, `dns1`, `fqdn`, `speed`, `manufact_info`, `status`, `link_state`) VALUES
(2, 'wlan0', 'bc:77:37:37:3c:78', '192.168.50.121', '54.245.235.245', '192.168.50.255', '255.255.255.0', '192.168.50.254', '192.168.50.254', 'lakestone-laptop2.lakestoneinno.com', '100fd', NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `journal`
--

CREATE TABLE IF NOT EXISTS `journal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_watch_id` int(11) NOT NULL COMMENT 'ID from watches table',
  `date` datetime DEFAULT NULL,
  `posix_time` int(11) NOT NULL,
  `file` text,
  `type` enum('file','dir','char','dev','link','unknown') DEFAULT NULL,
  `fk_event_ed` int(50) NOT NULL COMMENT 'Id for file event from event table',
  `uid` varchar(100) DEFAULT NULL,
  `uid_name` varchar(50) DEFAULT NULL COMMENT 'Actual user name for uid given in uid field',
  `gid` varchar(100) DEFAULT NULL,
  `gid_name` varchar(50) DEFAULT NULL COMMENT 'Actual group name for given gid numger in gid field',
  `accessed_by` varchar(150) DEFAULT NULL,
  `posix_permissions` int(8) DEFAULT NULL,
  `acl_permissions` text,
  `size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COMMENT='Temporary storage for all file changes under watch directory' AUTO_INCREMENT=6885 ;

--
-- Dumping data for table `journal`
--

INSERT INTO `journal` (`id`, `fk_watch_id`, `date`, `posix_time`, `file`, `type`, `fk_event_ed`, `uid`, `uid_name`, `gid`, `gid_name`, `accessed_by`, `posix_permissions`, `acl_permissions`, `size`) VALUES
(6766, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/4913', '', 0, '', '', '', '', NULL, 0, NULL, 0),
(6767, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/4913', '', 0, '', '', '', '', NULL, 0, NULL, 0),
(6768, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/4913', '', 0, '', '', '', '', NULL, 0, NULL, 0),
(6769, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/4913', '', 0, '', '', '', '', NULL, 0, NULL, 0),
(6770, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6771, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt~', '', 0, '', '', '', '', NULL, 0, NULL, 95),
(6772, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6773, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6774, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6775, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6776, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 95),
(6777, 1, '2008-12-15 20:57:14', 0, ' /home/mlee/test.txt~', '', 0, '', '', '', '', NULL, 0, NULL, 95),
(6778, 1, '2008-12-15 20:57:51', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6779, 1, '2008-12-15 20:57:51', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6780, 1, '2008-12-15 22:10:35', 0, ' /home/mlee/eclipse/plugins/com.ibm.icu_3.6.1.v20070906.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4072434),
(6781, 1, '2008-12-15 22:10:41', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6782, 1, '2008-12-15 22:10:41', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6783, 1, '2008-12-15 22:12:10', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6784, 1, '2008-12-15 22:12:10', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 1486442),
(6785, 1, '2008-12-15 22:12:10', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 1486442),
(6786, 1, '2008-12-15 22:12:22', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6787, 1, '2008-12-15 22:12:22', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6788, 1, '2008-12-15 23:51:45', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6789, 1, '2008-12-15 23:51:45', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6790, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6791, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6792, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6793, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6794, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6795, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6796, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6797, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6798, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6799, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6800, 1, '2008-12-15 23:52:05', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10405),
(6801, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6802, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6803, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6804, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6805, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6806, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6807, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6808, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6809, 1, '2008-12-15 23:52:27', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6810, 1, '2008-12-15 23:52:28', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6811, 1, '2008-12-15 23:52:28', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6812, 1, '2008-12-15 23:52:28', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10393),
(6813, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6814, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6815, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6816, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6817, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6818, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6819, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6820, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6821, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6822, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6823, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6824, 1, '2008-12-15 23:52:56', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10271),
(6825, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6826, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6827, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6828, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6829, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6830, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6831, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6832, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6833, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6834, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6835, 1, '2008-12-15 23:53:08', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10242),
(6836, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6837, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6838, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6839, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6840, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6841, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6842, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6843, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6844, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6845, 1, '2008-12-15 23:53:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6846, 1, '2008-12-15 23:53:33', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10139),
(6847, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6848, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6849, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6850, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6851, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6852, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6853, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6854, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6855, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6856, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6857, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6858, 1, '2008-12-15 23:53:59', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 10017),
(6859, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6860, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6861, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6862, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6863, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6864, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6865, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6866, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6867, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6868, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6869, 1, '2008-12-15 23:54:32', 0, ' /home/mlee/workspace/afrs/includes/network/StreamSocketServer.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 9780),
(6870, 1, '2008-12-15 23:54:33', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6871, 1, '2008-12-15 23:54:33', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6872, 1, '2008-12-15 23:54:42', 0, ' /home/mlee/eclipse/plugins/org.eclipse.wst.sse.ui_1.0.305.v200802142230.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6873, 1, '2008-12-15 23:54:42', 0, ' /home/mlee/eclipse/plugins/org.eclipse.php.ui_1.0.3.v20080601.jar', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 726919),
(6874, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6875, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6876, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6877, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6878, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6879, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6880, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6881, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6882, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6883, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646),
(6884, 1, '2008-12-15 23:55:19', 0, ' /home/mlee/workspace/afrs/includes/system/Dispatcher.php', '', 0, ' 1000', ' mlee', ' 1000', ' mlee', NULL, 644, NULL, 4646);

-- --------------------------------------------------------

--
-- Table structure for table `mounts`
--

CREATE TABLE IF NOT EXISTS `mounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_device_id` int(11) NOT NULL,
  `mount_path` varchar(255) NOT NULL,
  `is_mounted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mount_path_mounted` (`mount_path`,`is_mounted`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COMMENT='Basicall our version of the fstab file in Linux' AUTO_INCREMENT=7 ;

--
-- Dumping data for table `mounts`
--

INSERT INTO `mounts` (`id`, `fk_device_id`, `mount_path`, `is_mounted`) VALUES
(6, 23, '/', 1);

-- --------------------------------------------------------

--
-- Table structure for table `partnershares`
--

CREATE TABLE IF NOT EXISTS `partnershares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clientid` varchar(100) NOT NULL,
  `share_name` varchar(50) NOT NULL,
  `size` int(11) NOT NULL,
  `available_size` int(11) NOT NULL,
  `active` binary(1) NOT NULL,
  `permission` enum('rw','r','w') NOT NULL,
  `creation_date` datetime NOT NULL,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_share_name` (`clientid`,`share_name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `priorityqueue`
--

CREATE TABLE IF NOT EXISTS `priorityqueue` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `system_priority_number` enum('1','2','3','4') NOT NULL,
  `bandwidth` int(11) NOT NULL COMMENT 'in kb/s',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `registry`
--

CREATE TABLE IF NOT EXISTS `registry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `value` varchar(2048) NOT NULL,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=15 ;

--
-- Dumping data for table `registry`
--

INSERT INTO `registry` (`id`, `name`, `value`, `notes`) VALUES
(1, 'last_sanity_check', '0', ''),
(14, 'offline_failed_checkin_count', '5', 'The number of failed checkin attempts before marking the sync partner offline.'),
(3, 'daemon_port_number', '4747', ''),
(4, 'watcher_port_number', '4746', ''),
(5, 'sid', '387d97a93c00b846e87ff', ''),
(7, 'timezone', '-7', ''),
(8, 'afrs_version', '0.1', ''),
(9, 'session_expires', '60', 'The amount of time, in seconds, that a session will wait for a response before it is considered to be expired.'),
(10, 'checkin_interval', '60', 'The amount of time, in seconds, that this server will wait before it checks-in with its member servers.'),
(11, 'accept_unsolicited_register_requests', '1', 'Whether we are to accept sync requests that were not originally initiated by this server.'),
(12, 'register_requests_per_second', '5', 'How many registers requests will be accepted per second.  The others will be discarded.'),
(13, 'key', '387d97a93c00b846e87ff387d97a93c00b846e87ff387d97a93c00b846e87ff', 'The key used in communication with the remote client.');

-- --------------------------------------------------------

--
-- Table structure for table `shares`
--

CREATE TABLE IF NOT EXISTS `shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `watches_id` int(11) NOT NULL,
  `share_name` varchar(50) NOT NULL,
  `size` int(11) NOT NULL,
  `available_size` int(11) NOT NULL,
  `active` binary(1) NOT NULL,
  `permission` enum('rw','r','w') NOT NULL COMMENT 'Permission in Octet value for this share 4,2=6 etc.',
  `creation_date` datetime NOT NULL COMMENT 'Date and time this share was added',
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_name` (`share_name`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `shares`
--

INSERT INTO `shares` (`id`, `watches_id`, `share_name`, `size`, `available_size`, `active`, `permission`, `creation_date`) VALUES
(1, 1, 'mikes_home', 2000000, 60000000, '1', '', '2009-12-15 22:10:52'),
(2, 2, 'root_home', 45000000, 2147483647, '1', '', '2010-01-10 11:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `sharespermissions`
--

CREATE TABLE IF NOT EXISTS `sharespermissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_share_id` int(11) NOT NULL COMMENT 'Foreign key of the share id',
  `client_id` varchar(100) NOT NULL COMMENT 'Client ID hash',
  `permission` enum('r','w','rw') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fk_share_id` (`fk_share_id`,`client_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `sync`
--

CREATE TABLE IF NOT EXISTS `sync` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `syncpartnerregisterrequests`
--

CREATE TABLE IF NOT EXISTS `syncpartnerregisterrequests` (
  `clientid` varchar(100) NOT NULL DEFAULT '' COMMENT 'The unique id from the partner''s db',
  `date_requested` datetime NOT NULL COMMENT 'The date the sync partner was added.',
  `ip_address` varchar(15) NOT NULL,
  `public_ip_address` varchar(15) NOT NULL,
  `port_number` int(11) NOT NULL DEFAULT '4747' COMMENT 'Port number that AFRSD is running on the remote end.',
  `fqdn` varchar(100) NOT NULL COMMENT 'FQDN of the partner to sync from/to',
  `is_nated` tinyint(1) DEFAULT NULL COMMENT 'This tells us how we communicate with the remote sync partner.',
  `partner_public_key` text,
  `timezone_offset` int(11) DEFAULT NULL COMMENT 'Timezone offset to GMT',
  `afrs_version` varchar(50) DEFAULT NULL COMMENT 'Version of the remote partner',
  `bandwidth_up` float DEFAULT NULL COMMENT 'Bandwidth of remote partnet in Kbytes',
  `bandwidth_down` float DEFAULT NULL,
  `sync_bandwidth` float DEFAULT NULL,
  `priority` enum('high','medium','low') DEFAULT NULL,
  `initiator` int(11) NOT NULL COMMENT '1 = localhost, 2 = remote host',
  `transaction_id` varchar(100) NOT NULL,
  PRIMARY KEY (`clientid`),
  UNIQUE KEY `fqdn` (`fqdn`),
  UNIQUE KEY `unique_priv_pub_ip` (`ip_address`,`public_ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Information about partners that we sync watches with';

-- --------------------------------------------------------

--
-- Table structure for table `syncpartners`
--

CREATE TABLE IF NOT EXISTS `syncpartners` (
  `clientid` varchar(100) NOT NULL COMMENT 'The unique id from the partner''s db',
  `date_added` datetime NOT NULL COMMENT 'The date the sync partner was added.',
  `ip_address` varchar(15) NOT NULL,
  `public_ip_address` varchar(15) NOT NULL,
  `port_number` int(11) NOT NULL DEFAULT '4747' COMMENT 'Port number that AFRSD is running on the remote end.',
  `fqdn` varchar(100) NOT NULL COMMENT 'FQDN of the partner to sync from/to',
  `is_nated` tinyint(1) NOT NULL COMMENT 'This tells us how we communicate with the remote sync partner.',
  `partner_public_key` text NOT NULL,
  `timezone_offset` int(11) NOT NULL COMMENT 'Timezone offset to GMT',
  `status` binary(1) NOT NULL COMMENT 'The current up status of the partner',
  `last_checkin` datetime NOT NULL COMMENT 'Date and time of last checkin from partner',
  `failed_checkin_count` int(11) NOT NULL,
  `afrs_version` varchar(50) NOT NULL COMMENT 'Version of the remote partner',
  `bandwidth_up` float NOT NULL COMMENT 'Bandwidth of remote partnet in Kbytes',
  `bandwidth_down` float NOT NULL,
  `sync_bandwidth` float DEFAULT NULL,
  `priority` enum('high','medium','low') NOT NULL,
  PRIMARY KEY (`clientid`),
  UNIQUE KEY `fqdn` (`fqdn`),
  UNIQUE KEY `unique_priv_pub_ip` (`ip_address`,`public_ip_address`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Information about partners that we sync watches with';

-- --------------------------------------------------------

--
-- Table structure for table `syncpartnerwatches`
--

CREATE TABLE IF NOT EXISTS `syncpartnerwatches` (
  `sync_partner_id` int(11) NOT NULL,
  `fk_share_id` int(11) NOT NULL,
  `local_mode` enum('duplex','push'',pull') NOT NULL,
  `active` binary(1) NOT NULL,
  PRIMARY KEY (`sync_partner_id`,`fk_share_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `watches`
--

CREATE TABLE IF NOT EXISTS `watches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `watch_path` varchar(500) NOT NULL,
  `recursive` tinyint(1) NOT NULL COMMENT 'Set whether to recursively watch the watch path.',
  `watch_hidden_directories` tinyint(1) NOT NULL COMMENT 'Whether to watch hiddend directories or not',
  `watch_hidden_files` tinyint(1) NOT NULL COMMENT 'Whether to watch hidden files or not.',
  `exclusions_paterns` text NOT NULL COMMENT 'Don''t watch files/directories based on these regular expressions.',
  `follow_symbolic_links` tinyint(1) NOT NULL COMMENT 'Do we follow symbolic links and watch what they point to?',
  `filter_by_group_owner` varchar(255) DEFAULT NULL COMMENT 'Only report events for files/directories that are owned by this group.',
  `filter_by_user_owner` varchar(255) DEFAULT NULL COMMENT 'Only report events for files/directories that are owned by this user.',
  `allow_yelling` tinyint(1) NOT NULL COMMENT 'Do we report on fast successive file changes.',
  `date_added` datetime NOT NULL,
  `date_modified` datetime DEFAULT NULL,
  `sync` binary(1) NOT NULL COMMENT 'Whether or not this watch is to be synced',
  `active` binary(1) NOT NULL,
  `frequency` int(11) NOT NULL COMMENT 'The amount of time in milliseconds to wait and check for file changes',
  `exclusion_patterns` varchar(255) DEFAULT NULL,
  `symbolic_links` tinyint(3) unsigned DEFAULT NULL,
  `ignore_zero_files` tinyint(3) unsigned DEFAULT NULL,
  `verbose` tinyint(3) unsigned DEFAULT NULL,
  `wait_amount` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_watch_path_events` (`watch_path`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=29 ;

-- --------------------------------------------------------

--
-- Table structure for table `watchhistory`
--

CREATE TABLE IF NOT EXISTS `watchhistory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_watch_id` int(11) NOT NULL COMMENT 'ID from watches table',
  `date` datetime DEFAULT NULL,
  `file` text,
  `type` enum('file','dir','char','dev','link') DEFAULT NULL,
  `fk_event_id` int(11) NOT NULL COMMENT 'Id for file event from event table',
  `uid` varchar(100) DEFAULT NULL,
  `uid_name` varchar(50) DEFAULT NULL COMMENT 'Actual user name for uid given in uid field',
  `gid` varchar(100) DEFAULT NULL,
  `gid_name` varchar(50) DEFAULT NULL COMMENT 'Actual group name for given gid numger in gid field',
  `accessed_by` varchar(150) NOT NULL,
  `posix_permissions` int(8) DEFAULT NULL,
  `acl_permissions` text,
  `size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Temporary storage for all file changes under watch directory' AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
