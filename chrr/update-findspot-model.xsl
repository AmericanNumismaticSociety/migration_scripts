<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:gml="http://www.opengis.net/gml"
	xmlns:nh="http://nomisma.org/nudsHoard" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:atom="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended" xmlns:nuds="http://nomisma.org/nuds"
	exclude-result-prefixes="xsl xs nh atom gsx" version="2.0">

	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:variable name="recordId" select="//nh:recordId"/>

	<xsl:variable name="findspot" as="node()*">
		<xsl:copy-of
			select="document('https://spreadsheets.google.com/feeds/list/160AJLx6bRLr4LOY0uwUCpSIfvv5iivYE6GzOk9KrLNw/oohuefc/public/full')//atom:entry[gsx:hoard = concat('http://numismatics.org/chrr/id/', $recordId)]"
		/>
	</xsl:variable>


	<xsl:template match="@* | node()">
		<xsl:copy>
			<xsl:apply-templates select="@* | node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="nh:nudsHoard">
		<nudsHoard xmlns="http://nomisma.org/nudsHoard" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:nuds="http://nomisma.org/nuds"
			xmlns:gml="http://www.opengis.net/gml" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		>
			<xsl:apply-templates/>
		</nudsHoard>
	</xsl:template>

	<xsl:template match="nh:maintenanceHistory">
		<xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nudsHoard">
			<xsl:apply-templates/>

			<maintenanceEvent xmlns="http://nomisma.org/nudsHoard">
				<eventType>derived</eventType>
				<eventDateTime standardDateTime="{current-dateTime()}">
					<xsl:value-of select="format-dateTime(current-dateTime(), '[D1] [MNn] [Y0001] [H01]:[m01]:[s01]:[f01]')"/>
				</eventDateTime>
				<agentType>machine</agentType>
				<agent>XSLT</agent>
				<eventDescription>Updated findspot model to reduce preprocessing.</eventDescription>
			</maintenanceEvent>
		</xsl:element>
	</xsl:template>

	<!-- suppress contentsDesc without any contents -->
	<xsl:template match="nh:contentsDesc[not(nh:contents/*)]"/>

	<xsl:template match="nh:findspot">
		<xsl:if test="$findspot[string(gsx:findspotname)]">
			<findspot xmlns="http://nomisma.org/nudsHoard">
				<xsl:apply-templates select="$findspot"/>
				
				
			</findspot>
		</xsl:if>



	</xsl:template>
	
	<xsl:template match="atom:entry">
		<xsl:element name="description" namespace="http://nomisma.org/nudsHoard">
			<xsl:attribute name="xml:lang">en</xsl:attribute>
			<xsl:value-of select="gsx:findspotname"/>
		</xsl:element>
		
		<xsl:if test="string(gsx:canonicalgeonamesuri)">
			<xsl:element name="fallsWithin" namespace="http://nomisma.org/nudsHoard">
				<xsl:if test="string(gsx:gml-compliantcoordinates)">
					<xsl:apply-templates select="gsx:gml-compliantcoordinates"/>
				</xsl:if>
				
				<xsl:element name="geogname" namespace="http://nomisma.org/nudsHoard">
					<xsl:attribute name="xlink:type">simple</xsl:attribute>
					<xsl:attribute name="xlink:role">findspot</xsl:attribute>
					<xsl:attribute name="xlink:href" select="gsx:canonicalgeonamesuri"/>
					<xsl:value-of select="gsx:placename"/>
				</xsl:element>
				
				<xsl:if test="string(gsx:aaturi)">
					<xsl:element name="type" namespace="http://nomisma.org/nudsHoard">
						<xsl:attribute name="xlink:type">simple</xsl:attribute>
						<xsl:attribute name="xlink:href" select="gsx:aaturi"/>
						<xsl:value-of select="gsx:aatlabel"/>
					</xsl:element>
				</xsl:if>
			</xsl:element>			
		</xsl:if>		
	</xsl:template>
	
	<xsl:template match="gsx:gml-compliantcoordinates">
		<gml:location>
			<xsl:choose>
				<xsl:when test="contains(., ' ')">
					<gml:Polygon>
						<gml:coordinates>
							<xsl:value-of select="normalize-space(.)"/>
						</gml:coordinates>
					</gml:Polygon>
				</xsl:when>
				<xsl:otherwise>
					<gml:Point>
						<gml:coordinates>
							<xsl:value-of select="normalize-space(.)"/>
						</gml:coordinates>
					</gml:Point>
				</xsl:otherwise>
			</xsl:choose>
		</gml:location>		
	</xsl:template>
	
</xsl:stylesheet>
