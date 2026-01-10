import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

/**
 * Generates a PDF from an HTML element
 * @param {HTMLElement} element - The DOM element to convert to PDF
 * @param {string} filename - The name of the file to save
 * @returns {Promise<File>} - Promise resolving to the PDF file
 */
export const generatePDF = async (element, filename = 'document.pdf') => {
    // Basic scaling for better quality
    const scale = 2;

    // Save original styles
    const originalOpacity = element.style.opacity;
    const originalZIndex = element.style.zIndex;
    const originalPosition = element.style.position;
    
    // Make visible for capture
    element.style.opacity = '1';
    element.style.zIndex = '9999';
    // Ensure it has a white background
    if (!element.style.background) {
        element.style.background = '#ffffff';
    }

    const canvas = await html2canvas(element, {
        scale: scale,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff'
    });

    // Restore original styles
    element.style.opacity = originalOpacity;
    element.style.zIndex = originalZIndex;
    if (originalPosition) element.style.position = originalPosition;

    const imgData = canvas.toDataURL('image/png');
    
    // Calculate PDF dimensions maintaining aspect ratio
    // A4 size: 210mm x 297mm
    const imgWidth = 210; 
    const pageHeight = 295; 
    const imgHeight = (canvas.height * imgWidth) / canvas.width;
    let heightLeft = imgHeight;
    let position = 0;

    const doc = new jsPDF('p', 'mm');
    
    // Add first page
    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
    heightLeft -= pageHeight;

    // Add subsequent pages if content is long
    while (heightLeft >= 0) {
        position = heightLeft - imgHeight;
        doc.addPage();
        doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
    }

    // Return as File object for sharing
    const pdfBlob = doc.output('blob');
    return new File([pdfBlob], filename, { type: 'application/pdf' });
};

/**
 * Downloads the PDF directly
 * @param {HTMLElement} element - The DOM element to convert
 * @param {string} filename - The name of the file to save
 */
export const downloadPDF = async (element, filename = 'document.pdf') => {
    const file = await generatePDF(element, filename);
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

/**
 * Shares the PDF using navigator.share if available
 * @param {HTMLElement} element - The DOM element to convert
 * @param {string} filename - The name of the file to share
 * @param {string} title - Title for the share dialog
 */
export const sharePDF = async (element, filename = 'document.pdf', title = 'Compartir Documento') => {
    try {
        const file = await generatePDF(element, filename);

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: title,
                text: 'Aquí tienes el documento adjunto.',
            });
            return true;
        } else {
            // Fallback: download if sharing is not supported
            await downloadPDF(element, filename);
            alert('Tu dispositivo no soporta compartir archivos directamente. El archivo se ha descargado.');
            return false;
        }
    } catch (error) {
        console.error('Error sharing PDF:', error);
        // Only alert if it's not a user cancellation
        if (error.name !== 'AbortError') {
            alert('Hubo un error al intentar compartir el archivo.');
        }
        return false;
    }
};
