<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:nuds="http://nomisma.org/nuds"
    exclude-result-prefixes="#all"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    
    <xsl:output indent="yes"/>
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="nuds:persname[@xlink:role='deity']">
       <xsl:element name="persname" namespace="http://nomisma.org/nuds">
           <xsl:attribute name="xlink:role">deity</xsl:attribute>
           <xsl:attribute name="xlink:type">simple</xsl:attribute>
           <xsl:attribute name="xlink:href">
               <xsl:choose>
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/56988'">http://nomisma.org/id/apollo</xsl:when>
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/57039'">http://nomisma.org/id/artemis</xsl:when>
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/57060'">http://nomisma.org/id/athena</xsl:when>
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/60915'">http://nomisma.org/id/nike</xsl:when>                   
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/58628'">http://nomisma.org/id/heracles</xsl:when>
                   <xsl:when test="@xlink:href = 'http://collection.britishmuseum.org/id/person-institution/58921'">http://nomisma.org/id/zeus</xsl:when>
               </xsl:choose>
           </xsl:attribute>
           
           <xsl:value-of select="."/>
       </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:recordId">
        <xsl:variable name="id" select="."/>
        
        <xsl:element name="recordId" namespace="http://nomisma.org/nuds">
            <xsl:value-of select="."/>
        </xsl:element>
        
        <!-- insert other record IDs -->
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="localType">typeNumber</xsl:attribute>
            <xsl:value-of select="substring-after($id, 'price.')"/>
        </xsl:element>
        
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="localType">sortId</xsl:attribute>
            
            <xsl:analyze-string select="substring-after($id, 'price.')" regex="([A-Z])?([0-9]+)([A-z]+)?">
                <xsl:matching-substring>
                    <xsl:variable name="ruler-num">
                        <xsl:choose>
                            <xsl:when test="regex-group(1) = 'P'">02</xsl:when>
                            <xsl:when test="regex-group(1) = 'L'">03</xsl:when>
                            <xsl:otherwise>01</xsl:otherwise>
                        </xsl:choose>
                    </xsl:variable>
                    
                    <xsl:value-of select="concat($ruler-num, '-', format-number(number(regex-group(2)), '0000'), regex-group(3))"/>
                </xsl:matching-substring>
                <xsl:non-matching-substring>
                    <xsl:value-of select="."/>
                </xsl:non-matching-substring>
            </xsl:analyze-string>
        </xsl:element>
        
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceHistory">
        <xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates/>
            
            <maintenanceEvent xmlns="http://nomisma.org/nuds">
                <eventType>derived</eventType>
                <eventDateTime standardDateTime="{current-dateTime()}">
                    <xsl:value-of select="format-dateTime(current-dateTime(), '[D1] [MNn] [Y0001] [H01]:[m01]:[s01]:[f01]')"/>
                </eventDateTime>
                <agentType>machine</agentType>
                <agent>XSLT</agent>
                <eventDescription>Replaced BM with Nomisma deity URIs; inserted sortId and typeNumber.</eventDescription>
            </maintenanceEvent>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:typeDesc">
        <xsl:element name="typeDesc" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates/>
            
            <typeSeries xmlns="http://nomisma.org/nuds" xlink:type="simple" xlink:href="http://nomisma.org/id/price1991">Price (1991)</typeSeries>
        </xsl:element>
    </xsl:template>
    
    <!-- refDesc no longer necessary here to capture the generate type series (moved to typeSeries in typeDesc; references should point to type numbers -->
    <xsl:template match="nuds:refDesc"/>
    
</xsl:stylesheet>