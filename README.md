# GLPI2MDT

## Introduction

This goal of this plugin is to interface GLPI with MDT, the Microsoft Deployment Toolkit in a way quite similar to what SSCM can do.

My goal when creating this tool was to enable our support team to reinstall computers using PXE boot with minimal interaction with any other tool than GLPI. Ideally I would even like end-users to be able to reinstall their computer "in place" just pressing F12 after contacting the support.

In order to work well the plugin needs to have as much informations as possible on the computers themselves so it can push the proper settings to MDT, namely:
- Make and model (for drivers)
- Hardware configuration (mac addresses)
- Type (I want to enable BitLocker for laptops for example)
- GUID, serial number, name.
- OS to deploy (windows or even servers)
- Applications
- Roles, packages (as in MDT)

## Restrictions

This plugin works fine on my specific configuration 
* MDT on Windows Server 2022
*  MS-SQL Server 2022
*  GLPI 11.0.7 on Debian 13
*  PHP 8.5

It is developed and tested in this environment only. If you experience problem with a different configuration please report the issue on GitHub. I'll do my best to make it compatible with other setups as long as I am aware of the issue.

## Prerequisites

* MDT must be installed in a MS-SQL database accessible from the GLPI server
* MDT must be fully operational by itself. The plugin will not fix a faulty MDT installation, it is only remote-controlling it.
* SQLSRV and SimpleXML PHP modules must be installed
* The "Control" directory in your MDT deployment share contains part of the MDT configuration (the other part is in the MS-SQL database). It needs to be mounted (read-only is OK) somewhere on your GLPI server and accessible to PHP scripts. Pay specific attention to files ownership and SELinux settings which can prevent proper functioning.
* Many modifications are needed to make the operating system choice possible. (See INSTALL.md file !)

The plugin is available in French and English.

## TODOs

* Manage ranks in applications (this is mainly UI interface stuff, the code can already handle it. My main question is "is it really needed? We don't as we don't have dependencies between applications (and no so many applications to install anyway).
* Handle models, packages in the same way applications are handled (but are you using those features in MDT)? Models are an issue because of a limitation in Fusion Inventory. It may be alleviated with GLPI 9.2.x. More to come...
* Automate some actions based on information available in GLPI and not managed by MDT (location, entity....)
* Be multi-MDT-server, multi-deployment-share, multi-domain aware. Currently the plugin is not domain aware and is connected to only one MDT database. This raises quite a few questions as to how it should work then.
* Several coupling modes are proposed in the config page.... from the ligthest to the tighter link between MDT and GLPI. 
* Fix Bugs!!!!!

Please test and send feedback if you (dis)like my work.
