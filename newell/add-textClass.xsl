<?xml version="1.0" encoding="UTF-8"?>

<!--Author: Ethan Gruber 
	Function: Add profileDesc. Move abstract into profileDesc. Add textClass for Getty AAT ID
	Date: July 2018	
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:tei="http://www.tei-c.org/ns/1.0"
	xmlns="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="xs tei" version="2.0">

	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="tei:teiHeader">
		<teiHeader xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates select="tei:fileDesc"/>
			<profileDesc>
				<xsl:if test="//tei:note[@type = 'abstract']">
					<abstract>
						<p>
							<xsl:value-of select="//tei:note[@type = 'abstract']/tei:p"/>
						</p>
					</abstract>
				</xsl:if>				
				<textClass>
					<classCode scheme="http://vocab.getty/edu.aat/">300264354</classCode>
				</textClass>
			</profileDesc>
			<xsl:apply-templates select="tei:revisionDesc"/>
		</teiHeader>
	</xsl:template>

	<xsl:template match="tei:revisionDesc">
		<revisionDesc xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<change when="2018-07-02">Moved abstract into profileDesc, added Getty AAT ID for genre.</change>
		</revisionDesc>
	</xsl:template>

	<!-- remove noteStmt with the abstract -->
	<xsl:template match="tei:notesStmt[tei:note[@type = 'abstract']]"/>
</xsl:stylesheet>
