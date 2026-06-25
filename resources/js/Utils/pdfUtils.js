import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

const waitForImages = async (element) => {
    const images = Array.from(element.querySelectorAll('img'));

    await Promise.all(images.map((image) => {
        if (image.complete && image.naturalWidth > 0) return Promise.resolve();

        return new Promise((resolve) => {
            const timeout = setTimeout(resolve, 5000);
            image.onload = () => {
                clearTimeout(timeout);
                resolve();
            };
            image.onerror = () => {
                clearTimeout(timeout);
                resolve();
            };
        });
    }));
};

const captureElement = async (element, scale = 2) => {
    await waitForImages(element);

    return html2canvas(element, {
        scale,
        useCORS: true,
        allowTaint: false,
        logging: false,
        backgroundColor: '#ffffff',
        imageTimeout: 15000,
        removeContainer: true,
    });
};

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

    const canvas = await captureElement(element);

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

export const downloadPagedPDF = async (element, filename = 'document.pdf', options = {}) => {
    const pages = Array.from(element.querySelectorAll('[data-pdf-page]'));
    const captureTargets = pages.length ? pages : [element];
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = 210;
    const pageHeight = 297;
    const scale = options.scale || 2;

    for (let index = 0; index < captureTargets.length; index += 1) {
        const page = captureTargets[index];
        const canvas = await captureElement(page, scale);
        const imgData = canvas.toDataURL('image/jpeg', 0.95);

        if (index > 0) {
            doc.addPage();
        }

        doc.addImage(imgData, 'JPEG', 0, 0, pageWidth, pageHeight);
    }

    doc.save(filename);
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
