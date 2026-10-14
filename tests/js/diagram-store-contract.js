/*
 * The DiagramStore contract (ADR-0019): behavior every adapter must show
 * through the interface alone. Run it against each adapter with a factory
 * that returns a fresh, empty-history store whose current content is
 * `initialXml`.
 */
import { describe, expect, it } from 'vitest';

const xml = label => `<definitions id="${label}"/>`;

export function describeDiagramStoreContract(name, makeStore) {
  describe(`${name} satisfies the DiagramStore contract`, () => {
    it('loads what was last saved', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      await store.save(xml('b'));

      expect(await store.load()).toBe(xml('b'));
    });

    it('adds one Version per save, newest first, numbered in sequence', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      await store.save(xml('b'));
      const versions = await store.versions();

      expect(versions.map(v => v.number)).toEqual([2, 1]);
      expect(versions.map(v => v.current)).toEqual([true, false]);
      versions.forEach(version => {
        expect(typeof version.id).toBe('string');
        expect(version.name).toBeTruthy();
        expect(Number.isNaN(Date.parse(version.savedAt))).toBe(false);
        expect(version.provisional).toBe(false);
      });
    });

    it('returns the content of any Version', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      await store.save(xml('b'));
      const [newer, older] = await store.versions();

      expect(await store.versionContent(older.id)).toBe(xml('a'));
      expect(await store.versionContent(newer.id)).toBe(xml('b'));
    });

    it('rejects the content of an unknown Version', async () => {
      const store = await makeStore();

      await expect(store.versionContent('unknown')).rejects.toThrow();
    });

    it('restores a Version as a new current Version, keeping the history', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      await store.save(xml('b'));
      const [, first] = await store.versions();

      await store.restoreVersion(first.id);
      const versions = await store.versions();

      expect(versions.map(v => v.number)).toEqual([3, 2, 1]);
      expect(versions[0].current).toBe(true);
      expect(await store.load()).toBe(xml('a'));
      expect(await store.versionContent(versions[0].id)).toBe(xml('a'));
    });

    it('renames a Version without changing its number', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      const [version] = await store.versions();

      await store.renameVersion(version.id, 'Approved by the board');
      const [renamed] = await store.versions();

      expect(renamed.id).toBe(version.id);
      expect(renamed.number).toBe(version.number);
      expect(renamed.name).toBe('Approved by the board');
    });

    it('refuses to promote or delete a Version that is not provisional', async () => {
      const store = await makeStore();

      await store.save(xml('a'));
      const [version] = await store.versions();

      await expect(store.promoteVersion(version.id)).rejects.toThrow();
      await expect(store.deleteVersion(version.id)).rejects.toThrow();
      expect((await store.versions()).map(v => v.id)).toEqual([version.id]);
    });
  });
}
