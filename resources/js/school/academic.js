import { http } from '../lib/http';

/**
 * Loads the full structure (classes, sections, subjects) of one academic
 * year. The years themselves are in the academicYear store.
 */
export async function loadYearStructure(yearId) {
    const { data } = await http.get(`/api/v1/academic-years/${yearId}`);

    return data.data;
}

/**
 * Flat list of sections for dropdowns: [{ id, label, classId, className, enrolled, capacity, full }].
 */
export function sectionOptions(year) {
    return (year?.classes ?? []).flatMap((schoolClass) =>
        schoolClass.sections.map((section) => ({
            id: section.id,
            classId: schoolClass.id,
            className: schoolClass.name,
            label: `${schoolClass.name} ${section.name}`,
            enrolled: section.enrolled_count,
            capacity: section.capacity,
            full: section.capacity !== null && section.enrolled_count >= section.capacity,
        })),
    );
}
