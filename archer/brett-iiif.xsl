<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:ead="urn:isbn:1-931666-22-9" xmlns="urn:isbn:1-931666-22-9"
	xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xs="http://www.w3.org/2001/XMLSchema" exclude-result-prefixes="xsl xs ead" version="2.0">
	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<!--<xsl:template match="ead:eadheader">
		<xsl:element name="eadheader" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates/>

			<!-\- revisiondesc -\->
			<revisiondesc>
				<change>
					<date normal="{substring(string(current-date()), 1, 10)}">
						<xsl:value-of select="format-date(current-date(), '[D1] [MNn] [Y0001]')"/>
					</date>
					<item>Refactored daogrps to link to IIIF services</item>
				</change>
			</revisiondesc>
		</xsl:element>
	</xsl:template>-->
	
	<xsl:template match="ead:c[@level='series']">
		<xsl:element name="c" namespace="urn:isbn:1-931666-22-9">
			<xsl:attribute name="id" select="@id"/>
			<xsl:attribute name="level">series</xsl:attribute>
			
			<xsl:apply-templates select="*[not(local-name()='c')]"/>
			<xsl:apply-templates select="ead:c">
				<xsl:sort select="ead:did/ead:unitid"/>
			</xsl:apply-templates>
		</xsl:element>
	</xsl:template>

	<!--<xsl:template match="ead:daogrp[parent::ead:c]">
		<xsl:variable name="id" select="parent::node()/ead:did/ead:unitid"/>

		<xsl:element name="daogrp" namespace="urn:isbn:1-931666-22-9">
			<xsl:apply-templates select="ead:daodesc"/>
			<daoloc xlink:type="locator" xlink:href="http://numismatics.org/archivesimages/thumbnail/{$id}.jpg" xlink:role="thumbnail"/>
			<daoloc xlink:type="locator" xlink:href="http://numismatics.org/archivesimages/reference/{$id}.jpg" xlink:role="reference"/>
			<daoloc xlink:type="locator" xlink:href="http://images.numismatics.org/archivesimages%2Farchive%2F{$id}.jpg" xlink:role="IIIFService"/>
		</xsl:element>
	</xsl:template>-->

</xsl:stylesheet>
