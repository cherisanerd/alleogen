import React, { useState } from 'react';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Download, Loader2 } from "lucide-react";
import { motion } from "framer-motion";

export default function FileGenerator() {
    const [formData, setFormData] = useState({
        businessName: '',
        contactEmail: '',
        websiteUrl: '',
        description: ''
    });
    const [isGenerating, setIsGenerating] = useState(false);
    const [downloadUrl, setDownloadUrl] = useState(null);
    const [preview, setPreview] = useState('');

    const generateTemplate = () => {
        return `========================================
BUSINESS INFORMATION REPORT
Generated: ${new Date().toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
})}
========================================

COMPANY DETAILS
---------------
Business Name: ${formData.businessName || '[Not Provided]'}
Website: ${formData.websiteUrl || '[Not Provided]'}
Contact Email: ${formData.contactEmail || '[Not Provided]'}

DESCRIPTION
-----------
${formData.description || '[No description provided]'}

========================================
End of Report
========================================`;
    };

    const handleInputChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        setDownloadUrl(null);
    };

    const handlePreview = () => {
        setPreview(generateTemplate());
    };

    const handleGenerate = async () => {
        setIsGenerating(true);
        
        // Simulate processing delay for UX
        await new Promise(resolve => setTimeout(resolve, 800));
        
        const content = generateTemplate();
        
        // Create blob and download URL
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        setDownloadUrl(url);
        setPreview(content);
        setIsGenerating(false);
    };

    const handleDownload = () => {
        if (!downloadUrl) return;
        
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `${formData.businessName.replace(/\s+/g, '_') || 'business'}_report.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    return (
        <div className="min-h-screen bg-white">
            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Hero Section */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="text-center pt-20 pb-16"
                >
                    <h1 className="heading text-5xl md:text-7xl font-bold text-[#180029] tracking-tight mb-6">
                        Generate Business Reports
                    </h1>
                    <p className="text-xl text-[#180029]/60 max-w-2xl mx-auto">
                        Enter your business details and generate a formatted text file
                    </p>
                </motion.div>

                {/* Main Content */}
                <div className="max-w-3xl mx-auto pb-24">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.1 }}
                        className="space-y-8"
                    >
                        {/* Input Form - Minimal Style */}
                        <div className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="businessName" className="text-sm text-[#180029]/70">Business Name</Label>
                                <Input
                                    id="businessName"
                                    placeholder="Acme Corporation"
                                    value={formData.businessName}
                                    onChange={(e) => handleInputChange('businessName', e.target.value)}
                                    className="h-14 border-gray-200 rounded-xl text-lg focus:border-[#7700CC] focus:ring-[#7700CC]"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="websiteUrl" className="text-sm text-[#180029]/70">Website URL</Label>
                                <Input
                                    id="websiteUrl"
                                    placeholder="https://example.com"
                                    value={formData.websiteUrl}
                                    onChange={(e) => handleInputChange('websiteUrl', e.target.value)}
                                    className="h-14 border-gray-200 rounded-xl text-lg focus:border-[#7700CC] focus:ring-[#7700CC]"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="contactEmail" className="text-sm text-[#180029]/70">Contact Email</Label>
                                <Input
                                    id="contactEmail"
                                    type="email"
                                    placeholder="contact@example.com"
                                    value={formData.contactEmail}
                                    onChange={(e) => handleInputChange('contactEmail', e.target.value)}
                                    className="h-14 border-gray-200 rounded-xl text-lg focus:border-[#7700CC] focus:ring-[#7700CC]"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description" className="text-sm text-[#180029]/70">Description</Label>
                                <Textarea
                                    id="description"
                                    placeholder="Brief description of the business..."
                                    value={formData.description}
                                    onChange={(e) => handleInputChange('description', e.target.value)}
                                    className="min-h-[120px] border-gray-200 rounded-xl text-lg focus:border-[#7700CC] focus:ring-[#7700CC] resize-none"
                                />
                            </div>

                            {/* Action Buttons */}
                            <div className="flex gap-3 pt-4">
                                <Button
                                    variant="outline"
                                    onClick={handlePreview}
                                    className="flex-1 h-14 rounded-xl border-gray-300 text-base"
                                >
                                    Preview
                                </Button>
                                <Button
                                    onClick={handleGenerate}
                                    disabled={isGenerating || !formData.businessName}
                                    className="flex-1 h-14 rounded-xl bg-[#180029] hover:bg-[#180029]/90 text-white text-base"
                                >
                                    {isGenerating ? (
                                        <>
                                            <Loader2 className="w-5 h-5 mr-2 animate-spin" />
                                            Generating
                                        </>
                                    ) : (
                                        'Generate File'
                                    )}
                                </Button>
                            </div>
                        </div>

                        {/* Preview Area */}
                        {preview && (
                            <motion.div
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                className="bg-[#F5F5F7] rounded-2xl p-8 border border-gray-200"
                            >
                                <div className="flex items-center justify-between mb-4">
                                    <p className="text-sm text-[#180029]/70">Output Preview</p>
                                    {downloadUrl && (
                                        <Button
                                            onClick={handleDownload}
                                            size="sm"
                                            className="bg-[#7700CC] hover:bg-[#47007A] text-white rounded-lg h-9"
                                        >
                                            <Download className="w-4 h-4 mr-2" />
                                            Download
                                        </Button>
                                    )}
                                </div>
                                <div className="bg-white rounded-xl p-6 overflow-auto max-h-96 border border-gray-200">
                                    <pre className="text-[#180029] text-sm font-mono whitespace-pre-wrap">
                                        {preview}
                                    </pre>
                                </div>
                            </motion.div>
                        )}
                    </motion.div>
                </div>
            </div>
        </div>
    );
}