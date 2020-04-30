<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:gml="http://www.opengis.net/gml"
	xmlns:nh="http://nomisma.org/nudsHoard" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:atom="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended" xmlns:nuds="http://nomisma.org/nuds" xmlns:numishare="https://github.com/ewg118/numishare"
	exclude-result-prefixes="xsl xs nh atom gsx numishare" version="2.0">

	<xsl:strip-space elements="*"/>
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:variable name="recordId" select="//nh:recordId"/>

	<xsl:variable name="nudsGroup" as="element()*">
		<nudsGroup>
			<xsl:variable name="type_series" as="element()*">
				<list>
					<xsl:for-each select="distinct-values(descendant::nuds:typeDesc[string(@xlink:href)]/substring-before(@xlink:href, 'id/'))">
						<type_series>
							<xsl:value-of select="."/>
						</type_series>
					</xsl:for-each>
				</list>
			</xsl:variable>
			<xsl:variable name="type_list" as="element()*">
				<list>
					<xsl:for-each select="distinct-values(descendant::nuds:typeDesc[string(@xlink:href)]/@xlink:href)">
						<type_series_item>
							<xsl:value-of select="."/>
						</type_series_item>
					</xsl:for-each>
				</list>
			</xsl:variable>
			<xsl:for-each select="$type_series//type_series">
				<xsl:variable name="type_series_uri" select="."/>
				<xsl:variable name="id-param">
					<xsl:for-each select="$type_list//type_series_item[contains(., $type_series_uri)]">
						<xsl:value-of select="substring-after(., 'id/')"/>
						<xsl:if test="not(position() = last())">
							<xsl:text>|</xsl:text>
						</xsl:if>
					</xsl:for-each>
				</xsl:variable>
				<xsl:if test="string-length($id-param) &gt; 0">
					<xsl:for-each select="document(concat($type_series_uri, 'apis/getNuds?identifiers=', encode-for-uri($id-param)))//nuds:nuds">
						<object xlink:href="{$type_series_uri}id/{nuds:control/nuds:recordId}">
							<xsl:copy-of select="."/>
						</object>
					</xsl:for-each>
				</xsl:if>
			</xsl:for-each>
			<xsl:for-each select="descendant::nuds:typeDesc[not(string(@xlink:href))]">
				<object>
					<xsl:copy-of select="."/>
				</object>
			</xsl:for-each>
		</nudsGroup>
	</xsl:variable>

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
			xmlns:gml="http://www.opengis.net/gml" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
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

	<xsl:template match="nh:hoardDesc">
		<xsl:element name="hoardDesc" namespace="http://nomisma.org/nudsHoard">

			<xsl:if test="parent::node()/nh:contentsDesc/nh:contents/*">
				<xsl:call-template name="derive-closing-date"/>
			</xsl:if>

			<xsl:apply-templates/>
		</xsl:element>


	</xsl:template>

	<!-- suppress contentsDesc without any contents -->
	<xsl:template match="nh:contentsDesc[not(nh:contents/*)]"/>

	<!-- refactor the findspot model -->
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

	<xsl:template name="derive-closing-date">
		
		<xsl:variable name="certainty_codes" as="node()*">
			<certainty_codes>
				<code label="" position="before" accept="true">1</code>
				<code label="As " position="before" accept="false">2</code>
				<code label="Copy of " position="before" accept="false">3</code>
				<code label="Copy as " position="before" accept="false">4</code>
				<code label="As issue " position="before" accept="true">5</code>
				<code label="At least one of " position="before" accept="true">6</code>
				<code label=" (extraneous)" position="after" accept="false">7</code>
				<code label="" position="before" accept="false">8</code>
				<code label="At least one of " position="before" accept="false">9</code>
			</certainty_codes>
		</xsl:variable>


		<xsl:variable name="all-dates" as="element()*">
			<dates>
				<xsl:for-each select="parent::node()/descendant::nuds:typeDesc">
					<xsl:if test="index-of($certainty_codes//code[@accept = 'true'], @certainty)">
						<xsl:choose>
							<xsl:when test="string(@xlink:href)">
								<xsl:variable name="href" select="@xlink:href"/>
								<xsl:for-each select="$nudsGroup//object[@xlink:href = $href]/descendant::*/@standardDate">
									<xsl:if test="number(.)">
										<date>
											<xsl:value-of select="number(.)"/>
										</date>
									</xsl:if>
								</xsl:for-each>
							</xsl:when>
							<xsl:otherwise>
								<xsl:for-each select="parent::node()/descendant::*/@standardDate">
									<xsl:if test="number(.)">
										<date>
											<xsl:value-of select="number(.)"/>
										</date>
									</xsl:if>
								</xsl:for-each>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:if>
				</xsl:for-each>
			</dates>
		</xsl:variable>

		<!-- get date values for closing date -->
		<xsl:variable name="dates" as="element()*">
			<dates>
				<xsl:for-each select="distinct-values($all-dates//date)">
					<xsl:sort data-type="number"/>
					<date>
						<xsl:value-of select="number(.)"/>
					</date>
				</xsl:for-each>
			</dates>
		</xsl:variable>


		<xsl:if test="count($dates//date) &gt; 0">
			<xsl:variable name="gYear" select="$dates//date[last()]"/>
			
			<xsl:element name="closingDate" namespace="http://nomisma.org/nudsHoard">
				<xsl:element name="date" namespace="http://nomisma.org/nudsHoard">
					<xsl:attribute name="standardDate" select="format-number($gYear, '0000')"/>
					<xsl:value-of select="numishare:normalizeDate($gYear)"/>					
				</xsl:element>
			</xsl:element>
		</xsl:if>
		
	</xsl:template>

	<xsl:function name="numishare:normalizeDate">
		<xsl:param name="date"/>
		
		<xsl:if test="substring($date, 1, 1) != '-' and number(substring($date, 1, 4)) &lt; 500">
			<xsl:text>A.D. </xsl:text>
		</xsl:if>
		
		<xsl:choose>
			<xsl:when test="$date castable as xs:dateTime">
				<xsl:value-of select="format-dateTime($date, '[D] [MNn] [Y], [H01]:[m01]')"/>
			</xsl:when>
			<xsl:when test="$date castable as xs:date">
				<xsl:value-of select="format-date($date, '[D] [MNn] [Y]')"/>
			</xsl:when>
			<xsl:when test="$date castable as xs:gYearMonth">
				<xsl:variable name="normalized" select="xs:date(concat($date, '-01'))"/>
				<xsl:value-of select="format-date($normalized, '[MNn] [Y]')"/>
			</xsl:when>
			<xsl:when test="$date castable as xs:gYear or $date castable as xs:integer">
				<xsl:value-of select="abs(number($date))"/>
			</xsl:when>
		</xsl:choose>
		
		<xsl:if test="substring($date, 1, 1) = '-'">
			<xsl:text> B.C.</xsl:text>
		</xsl:if>
	</xsl:function>

</xsl:stylesheet>
