import { http } from '../lib/http';

/**
 * Loads academic years and the full structure (classes, sections, subjects)
 * of one year, the current year by default.
 */
export async function loadAcademicYears() {
    const { data } = await http.get('/api/v1/academic-years');

    return data.data;
}

export async function loadYearStructure(yearId) {
    const { data } = await http.get(`/api/v1/academic-years/${yearId}`);

    return data.data;
}

/**
 * Flat list of sections for dropdowns: [{ id, label, classId }].
 */
export function sectionOptions(year) {
    return (year?.classes ?? []).flatMap((schoolClass) =>
        schoolClass.sections.map((section) => ({
            id: section.id,
            classId: schoolClass.id,
            label: `${schoolClass.name} ${section.name}`,
            full: section.capacity !== null && section.enrolled_count >= section.capacity,
        })),
    );
}
