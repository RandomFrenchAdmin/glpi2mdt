' // ***************************************************************************
' // 
' // Copyright (c) Microsoft Corporation.  All rights reserved.
' // 
' // Microsoft Deployment Toolkit Solution Accelerator
' //
' // File:      DeployWiz_Initialization.vbs
' // 
' // Version:   6.3.8456.1000
' // 
' // Purpose:   Main Client Deployment Wizard Initialization routines
' // 
' // ***************************************************************************


Option Explicit


'''''''''''''''''''''''''''''''''''''
'  Image List
'

Dim g_AllOperatingSystems

Function AllOperatingSystems
	Dim oOSes
	If isempty(g_AllOperatingSystems) then
		set oOSes = new ConfigFile
		oOSes.sFileType = "OperatingSystems"
		oOSes.bMustSucceed = false
		set g_AllOperatingSystems = oOSes.FindAllItems
	End if
	set AllOperatingSystems = g_AllOperatingSystems
End function

Function InitializeOSList
	ButtonNext.Disabled = TRUE
	OSListBox.InnerHTML = oOperatingSystems.GetHTMLEx ( "Radio", "OSGUID" )
	PopulateElements
End function

Function OSItemChange
	Dim oInput
	ButtonNext.Disabled = TRUE
	for each oInput in document.getElementsByName("OSGUID")
		If oInput.checked Then
			oEnvironment.Item("OSGUID") = oInput.Value
			ButtonNext.Disabled = FALSE
			exit function
		End If
	next
End function

'''''''''''''''''''''''''''''''''''''
'  Validate task sequence List
'

Function ValidateOSList
	Dim oOS
	set oOS = new ConfigFile
	oOS.sFileType = "OperatingSystems"
	SaveAllDataElements
	oEnvironment.Item("OSValue") = oUtility.SelectSingleNodeString(oOS.FindAllItems.Item(oEnvironment.Item("OSGUID")),"./Name")
	oLogging.CreateEntry "OSValue is Now: " & oEnvironment.Item("OSValue"), LogTypeVerbose
	ValidateOSList = True
	ButtonNext.Disabled = False
End Function
